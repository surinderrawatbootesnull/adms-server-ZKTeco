<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Safe configuration lookup fallback
        $timezone = env('APP_TIMEZONE', 'UTC');

        // Run 1: Every day at 14:00 (2:00 PM)
        $schedule->command('attendance:calculate-daily')
            ->dailyAt('14:00')
            ->timezone($timezone);

        // Run 2: Every day at 22:30 (10:30 PM)
        $schedule->command('attendance:calculate-daily')
            ->dailyAt('22:30')
            ->timezone($timezone);
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}