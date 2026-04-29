<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\LoanSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessOverdueLoans extends Command
{
    protected $signature   = 'loans:process-overdue';
    protected $description = 'Mark overdue installments, apply penalties, and update arrears on all active loans.';

    public function handle(): int
    {
        $today = now()->toDateString();

        // 1. Mark unpaid/partial installments past their due date as overdue
        $marked = LoanSchedule::where('due_date', '<', $today)
            ->whereIn('status', ['pending', 'partial'])
            ->update(['status' => 'overdue']);

        $this->info("Marked {$marked} installment(s) as overdue.");

        // 2. Apply penalties to active loans that have overdue installments
        $overdueLoans = Loan::where('status', 'active')
            ->whereHas('schedule', fn ($q) => $q->where('status', 'overdue'))
            ->with(['loanProduct', 'schedule' => fn ($q) => $q->where('status', 'overdue')])
            ->get();

        $penaltiesApplied = 0;

        foreach ($overdueLoans as $loan) {
            DB::transaction(function () use ($loan, $today, &$penaltiesApplied) {
                $product = $loan->loanProduct;

                // Only apply a penalty if the product has one configured
                if (!$product || !$product->late_penalty_value) {
                    return;
                }

                // Avoid double-applying a penalty for today
                $alreadyApplied = $loan->penalties()
                    ->whereDate('applied_at', $today)
                    ->exists();

                if ($alreadyApplied) {
                    return;
                }

                // Calculate penalty amount
                $amount = match ($product->late_penalty_type) {
                    'percentage' => round($loan->outstanding_balance * ($product->late_penalty_value / 100), 2),
                    default      => $product->late_penalty_value, // flat
                };

                if ($amount <= 0) return;

                $loan->penalties()->create([
                    'amount'     => $amount,
                    'reason'     => 'Late payment penalty',
                    'applied_at' => $today,
                    'is_paid'    => false,
                ]);

                $penaltiesApplied++;
            });
        }

        $this->info("Applied penalties to {$penaltiesApplied} loan(s).");

        // 3. Refresh days_in_arrears and is_overdue on all active loans
        $updated = 0;
        Loan::where('status', 'active')->each(function (Loan $loan) use ($today, &$updated) {
            $oldestOverdue = $loan->schedule()
                ->where('status', 'overdue')
                ->orderBy('due_date')
                ->value('due_date');

            $daysInArrears = $oldestOverdue
                ? (int) now()->diffInDays($oldestOverdue, false) * -1
                : 0;

            $isOverdue = $daysInArrears > 0;

            $loan->update([
                'days_in_arrears' => max(0, $daysInArrears),
                'is_overdue'      => $isOverdue,
            ]);

            $updated++;
        });

        $this->info("Refreshed arrears on {$updated} active loan(s).");

        return Command::SUCCESS;
    }
}
