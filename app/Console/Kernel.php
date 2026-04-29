<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('loans:process-overdue')->dailyAt('01:00');
        $schedule->command('sms:send-reminders --days=1')->dailyAt('08:00');
        $schedule->command('sms:send-reminders --days=3')->dailyAt('08:05');
        $schedule->command('savings:accrue-interest')->monthlyOn(28, '23:00');
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
