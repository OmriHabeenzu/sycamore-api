<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanSchedule;
use App\Models\Repayment;
use Illuminate\Support\Facades\DB;

class RepaymentService
{
    /**
     * Record a payment and allocate it across due installments.
     * Allocation order: oldest overdue first → fees → interest → principal
     */
    public function record(Loan $loan, array $data): Repayment
    {
        return DB::transaction(function () use ($loan, $data) {
            $remaining = (float) $data['amount'];

            $principalPaid = 0;
            $interestPaid  = 0;
            $feePaid       = 0;
            $penaltyPaid   = 0;

            // Get unpaid installments oldest first
            $installments = LoanSchedule::where('loan_id', $loan->id)
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->orderBy('due_date')
                ->lockForUpdate()
                ->get();

            foreach ($installments as $installment) {
                if ($remaining <= 0) break;

                $feeBalance      = (float) $installment->fee_due      - (float) $installment->fee_paid;
                $interestBalance = (float) $installment->interest_due  - (float) $installment->interest_paid;
                $principalBalance= (float) $installment->principal_due - (float) $installment->principal_paid;

                // Pay fee first
                if ($feeBalance > 0 && $remaining > 0) {
                    $pay = min($feeBalance, $remaining);
                    $installment->fee_paid = round((float) $installment->fee_paid + $pay, 2);
                    $feePaid   += $pay;
                    $remaining  = round($remaining - $pay, 2);
                }

                // Then interest
                if ($interestBalance > 0 && $remaining > 0) {
                    $pay = min($interestBalance, $remaining);
                    $installment->interest_paid = round((float) $installment->interest_paid + $pay, 2);
                    $interestPaid += $pay;
                    $remaining     = round($remaining - $pay, 2);
                }

                // Then principal
                if ($principalBalance > 0 && $remaining > 0) {
                    $pay = min($principalBalance, $remaining);
                    $installment->principal_paid = round((float) $installment->principal_paid + $pay, 2);
                    $principalPaid += $pay;
                    $remaining      = round($remaining - $pay, 2);
                }

                $installment->total_paid = round(
                    (float) $installment->fee_paid +
                    (float) $installment->interest_paid +
                    (float) $installment->principal_paid,
                    2
                );

                // Update installment status
                if ($installment->total_paid >= $installment->total_due) {
                    $installment->status  = 'paid';
                    $installment->paid_at = now();
                } elseif ($installment->total_paid > 0) {
                    $installment->status = 'partial';
                }

                $installment->save();
            }

            // Generate receipt number: RCP-00001 per company
            $receiptCount = Repayment::where('company_id', $loan->company_id)->count();
            $receiptNo    = 'RCP-' . str_pad($receiptCount + 1, 5, '0', STR_PAD_LEFT);

            // Record the repayment
            $repayment = Repayment::create([
                'company_id'       => $loan->company_id,
                'loan_id'          => $loan->id,
                'receipt_no'       => $receiptNo,
                'received_by'      => $data['received_by'],
                'amount'           => $data['amount'],
                'principal_amount' => $principalPaid,
                'interest_amount'  => $interestPaid,
                'fee_amount'       => $feePaid,
                'penalty_amount'   => $penaltyPaid,
                'payment_date'     => $data['payment_date'],
                'payment_method'   => $data['payment_method'],
                'reference_number' => $data['reference_number'] ?? null,
                'notes'            => $data['notes'] ?? null,
            ]);

            // Update loan totals
            $totalPaid   = round((float) $loan->total_paid + (float) $data['amount'], 2);
            $outstanding = round((float) $loan->total_amount_due - $totalPaid, 2);

            $allPaid = LoanSchedule::where('loan_id', $loan->id)
                ->where('status', '!=', 'paid')
                ->doesntExist();

            $loan->update([
                'total_paid'          => $totalPaid,
                'outstanding_balance' => max(0, $outstanding),
                'status'              => $allPaid ? 'closed' : 'active',
            ]);

            return $repayment;
        });
    }

    /**
     * Update overdue status and days_in_arrears for a loan.
     * Called by a scheduled job or on-demand.
     */
    public function refreshArrears(Loan $loan): void
    {
        $today = now()->toDateString();

        // Mark overdue installments
        LoanSchedule::where('loan_id', $loan->id)
            ->whereIn('status', ['pending', 'partial'])
            ->where('due_date', '<', $today)
            ->update(['status' => 'overdue']);

        // Earliest unpaid overdue date
        $earliest = LoanSchedule::where('loan_id', $loan->id)
            ->where('status', 'overdue')
            ->orderBy('due_date')
            ->value('due_date');

        $daysInArrears = $earliest
            ? now()->diffInDays($earliest)
            : 0;

        $loan->update([
            'days_in_arrears' => $daysInArrears,
            'is_overdue'      => $daysInArrears > 0,
        ]);
    }
}
