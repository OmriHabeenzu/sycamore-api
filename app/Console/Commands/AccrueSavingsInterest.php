<?php

namespace App\Console\Commands;

use App\Models\SavingsAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AccrueSavingsInterest extends Command
{
    protected $signature   = 'savings:accrue-interest';
    protected $description = 'Post monthly interest to all active savings accounts that have an interest rate configured.';

    public function handle(): int
    {
        $today     = now()->toDateString();
        $processed = 0;
        $skipped   = 0;

        SavingsAccount::where('status', 'active')
            ->where('interest_rate', '>', 0)
            ->where('balance', '>', 0)
            ->each(function (SavingsAccount $account) use ($today, &$processed, &$skipped) {
                // Only post once per month — check if interest was already posted this calendar month
                $alreadyPosted = $account->transactions()
                    ->where('type', 'interest')
                    ->whereYear('transaction_date', now()->year)
                    ->whereMonth('transaction_date', now()->month)
                    ->exists();

                if ($alreadyPosted) {
                    $skipped++;
                    return;
                }

                // Monthly interest = balance × (annual_rate / 12 / 100)
                $monthlyInterest = round(
                    (float) $account->balance * ((float) $account->interest_rate / 12 / 100),
                    2
                );

                if ($monthlyInterest <= 0) {
                    $skipped++;
                    return;
                }

                DB::transaction(function () use ($account, $monthlyInterest, $today) {
                    $newBalance = round((float) $account->balance + $monthlyInterest, 2);

                    $account->transactions()->create([
                        'type'             => 'interest',
                        'amount'           => $monthlyInterest,
                        'balance_after'    => $newBalance,
                        'reference'        => null,
                        'notes'            => 'Monthly interest accrual @ ' . $account->interest_rate . '% p.a.',
                        'transaction_date' => $today,
                        'created_by'       => null,
                    ]);

                    $account->update(['balance' => $newBalance]);
                });

                $processed++;
            });

        $this->info("Interest posted to {$processed} account(s). Skipped {$skipped} (already posted or zero balance).");

        return Command::SUCCESS;
    }
}
