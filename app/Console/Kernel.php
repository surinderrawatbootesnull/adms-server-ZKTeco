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
        // Get 'APP_TIMEZONE' key from .env file
        $timezone = env('APP_TIMEZONE', 'UTC');

        // Place the automation command inside the schedule method where it belongs
        $schedule->command('attendance:calculate-daily')
            ->twiceDaily(14, 21)
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