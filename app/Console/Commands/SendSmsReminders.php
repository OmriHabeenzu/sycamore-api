<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Models\LoanSchedule;
use App\Services\SmsService;
use Illuminate\Console\Command;

class SendSmsReminders extends Command
{
    protected $signature   = 'sms:send-reminders {--days=1 : Days before due date to send reminder}';
    protected $description = 'Send SMS reminders for loans due in N days and overdue loans.';

    public function __construct(private SmsService $sms)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $daysAhead = (int) $this->option('days');
        $targetDate = now()->addDays($daysAhead)->toDateString();

        $sent = 0;

        // 1. Due-date reminders
        $dueSoon = LoanSchedule::where('due_date', $targetDate)
            ->whereIn('status', ['pending', 'partial'])
            ->with('loan.borrower')
            ->get();

        foreach ($dueSoon as $schedule) {
            $loan     = $schedule->loan;
            $borrower = $loan?->borrower;
            if (!$borrower || !$borrower->phone) continue;

            $amount = number_format($schedule->total_due, 2);
            $message = "Dear {$borrower->first_name}, your loan installment of K{$amount} "
                . "for loan {$loan->loan_no} is due on {$schedule->due_date}. "
                . "Please ensure timely payment. Thank you.";

            $this->sms->send($borrower->phone, $message, $loan->id, $borrower->id, $loan->company_id);
            $sent++;
        }

        $this->info("Sent {$sent} due-date reminder(s).");

        // 2. Overdue reminders
        $overdueSent = 0;
        $overdueLoans = Loan::where('status', 'active')
            ->where('is_overdue', true)
            ->with('borrower')
            ->get();

        foreach ($overdueLoans as $loan) {
            $borrower = $loan->borrower;
            if (!$borrower || !$borrower->phone) continue;

            $outstanding = number_format($loan->outstanding_balance, 2);
            $message = "Dear {$borrower->first_name}, your loan {$loan->loan_no} is "
                . "{$loan->days_in_arrears} day(s) overdue. "
                . "Outstanding balance: K{$outstanding}. "
                . "Please contact us urgently to avoid further penalties.";

            $this->sms->send($borrower->phone, $message, $loan->id, $borrower->id, $loan->company_id);
            $overdueSent++;
        }

        $this->info("Sent {$overdueSent} overdue reminder(s).");

        return Command::SUCCESS;
    }
}
