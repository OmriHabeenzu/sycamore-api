<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanSchedule;
use Carbon\Carbon;

class LoanScheduleService
{
    /**
     * Preview a schedule without persisting — used before disbursement.
     */
    public function preview(
        float $principal, float $rate, string $method,
        string $frequency, int $term, string $firstRepaymentDate
    ): array {
        // Build a temporary stdClass that looks like a Loan
        $loan = new \stdClass();
        $loan->principal_amount    = $principal;
        $loan->interest_rate       = $rate;
        $loan->interest_method     = $method;
        $loan->repayment_frequency = $frequency;
        $loan->term                = $term;
        $loan->first_repayment_date= $firstRepaymentDate;
        $loan->id                  = 0;

        $rows = $method === 'flat_rate'
            ? $this->flatRateSchedule($loan)
            : $this->reducingBalanceSchedule($loan);

        $totalInterest  = array_sum(array_column($rows, 'interest_due'));
        $totalAmountDue = array_sum(array_column($rows, 'total_due'));

        return [
            'installments'    => $rows,
            'total_interest'  => round($totalInterest, 2),
            'total_amount_due'=> round($totalAmountDue, 2),
        ];
    }

    /**
     * Generate and persist the repayment schedule for a loan.
     * Called at disbursement time.
     */
    public function generate(Loan $loan, ?float $principalOverride = null): void
    {
        // Delete any existing schedule (e.g. re-disbursement, top-up, restructure)
        $loan->schedule()->delete();

        // Temporarily override principal if needed (top-up / restructure)
        $originalPrincipal = null;
        if ($principalOverride !== null && $principalOverride != $loan->principal_amount) {
            $originalPrincipal = $loan->principal_amount;
            $loan->principal_amount = $principalOverride;
        }

        $installments = $loan->interest_method === 'flat_rate'
            ? $this->flatRateSchedule($loan)
            : $this->reducingBalanceSchedule($loan);

        // Restore original value if we overrode it
        if ($originalPrincipal !== null) {
            $loan->principal_amount = $originalPrincipal;
        }

        LoanSchedule::insert($installments);

        // Update loan totals from generated schedule
        $totalInterest   = array_sum(array_column($installments, 'interest_due'));
        $totalAmountDue  = array_sum(array_column($installments, 'total_due'));

        $loan->update([
            'total_interest'      => $totalInterest,
            'total_amount_due'    => round((float)$loan->total_paid + $totalAmountDue, 2),
            'outstanding_balance' => $totalAmountDue,
        ]);
    }

    /**
     * Flat Rate:
     *   Total interest = principal × rate × term (in periods)
     *   Each installment = equal principal + equal interest
     */
    private function flatRateSchedule(Loan $loan): array
    {
        $n         = $loan->term;
        $principal = (float) $loan->principal_amount;
        $rate      = (float) $loan->interest_rate / 100; // per period

        $totalInterest    = $principal * $rate * $n;
        $principalPerPart = round($principal / $n, 2);
        $interestPerPart  = round($totalInterest / $n, 2);

        $rows        = [];
        $dueDate     = Carbon::parse($loan->first_repayment_date);
        $now         = now()->toDateTimeString();

        for ($i = 1; $i <= $n; $i++) {
            // Last installment absorbs rounding
            $principalDue = ($i === $n)
                ? round($principal - ($principalPerPart * ($n - 1)), 2)
                : $principalPerPart;

            $interestDue = ($i === $n)
                ? round($totalInterest - ($interestPerPart * ($n - 1)), 2)
                : $interestPerPart;

            $rows[] = [
                'loan_id'        => $loan->id,
                'installment_no' => $i,
                'due_date'       => $dueDate->toDateString(),
                'principal_due'  => $principalDue,
                'interest_due'   => $interestDue,
                'fee_due'        => 0,
                'total_due'      => round($principalDue + $interestDue, 2),
                'principal_paid' => 0,
                'interest_paid'  => 0,
                'fee_paid'       => 0,
                'total_paid'     => 0,
                'status'         => 'pending',
                'paid_at'        => null,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];

            $dueDate = $this->nextDueDate($dueDate, $loan->repayment_frequency);
        }

        return $rows;
    }

    /**
     * Reducing Balance (Annuity method):
     *   Fixed payment = P × r(1+r)^n / ((1+r)^n - 1)
     *   Each period: interest = outstanding × r, principal = payment − interest
     */
    private function reducingBalanceSchedule(Loan $loan): array
    {
        $n         = $loan->term;
        $principal = (float) $loan->principal_amount;
        $r         = (float) $loan->interest_rate / 100; // per period

        // Annuity payment per period
        if ($r == 0) {
            $payment = round($principal / $n, 2);
        } else {
            $payment = round($principal * ($r * pow(1 + $r, $n)) / (pow(1 + $r, $n) - 1), 2);
        }

        $rows      = [];
        $balance   = $principal;
        $dueDate   = Carbon::parse($loan->first_repayment_date);
        $now       = now()->toDateTimeString();

        for ($i = 1; $i <= $n; $i++) {
            $interestDue  = round($balance * $r, 2);
            $principalDue = round($payment - $interestDue, 2);

            // Last installment: clear any rounding remainder
            if ($i === $n) {
                $principalDue = round($balance, 2);
                $payment      = round($principalDue + $interestDue, 2);
            }

            $rows[] = [
                'loan_id'        => $loan->id,
                'installment_no' => $i,
                'due_date'       => $dueDate->toDateString(),
                'principal_due'  => $principalDue,
                'interest_due'   => $interestDue,
                'fee_due'        => 0,
                'total_due'      => round($principalDue + $interestDue, 2),
                'principal_paid' => 0,
                'interest_paid'  => 0,
                'fee_paid'       => 0,
                'total_paid'     => 0,
                'status'         => 'pending',
                'paid_at'        => null,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];

            $balance  = round($balance - $principalDue, 2);
            $dueDate  = $this->nextDueDate($dueDate, $loan->repayment_frequency);
        }

        return $rows;
    }

    /**
     * Advance a date by one repayment period.
     */
    private function nextDueDate(Carbon $date, string $frequency): Carbon
    {
        return match ($frequency) {
            'daily'     => (clone $date)->addDay(),
            'weekly'    => (clone $date)->addWeek(),
            'biweekly'  => (clone $date)->addWeeks(2),
            'monthly'   => (clone $date)->addMonth(),
            'quarterly' => (clone $date)->addMonths(3),
        };
    }
}
