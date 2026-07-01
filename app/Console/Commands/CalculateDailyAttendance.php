<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AttendanceService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CalculateDailyAttendance extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'attendance:calculate-daily {date? : Optional historical targeted date using YYYY-MM-DD format}';

    /**
     * The console command description.
     */
    protected $description = 'Calculates total time in office for all employees and inserts summaries into the database';

    protected $attendanceService;

    public function __construct(AttendanceService $attendanceService)
    {
        parent::__construct();
        $this->attendanceService = $attendanceService;
    }

    public function handle()
    {
        // Safe configuration lookup (Production cache compliance)
        $timezone = config('app.timezone', 'UTC');
        
        // Grab argument if provided manually, else default to today
        $targetDate = $this->argument('date') ?: Carbon::today($timezone)->toDateString();

        $this->info("Starting attendance summary compilation calculations for date: {$targetDate}...");

        try {
            // Run core calculation service engine
            $result = $this->attendanceService->calculateDailyTotals($targetDate);
            $calculatedTotals = $result['calculated_totals'] ?? [];
        } catch (\Exception $e) {
            $this->error("Critical Failure: The calculation engine crashed during initialization. Error: {$e->getMessage()}");
            return Command::FAILURE;
        }

        if (empty($calculatedTotals)) {
            $this->warn("No active employee logs parsed for date ({$targetDate}).");
            return Command::SUCCESS;
        }

        $now = Carbon::now($timezone);

        // Loop through calculations with individual row safeguards
        foreach ($calculatedTotals as $employeeId => $totalTime) {
            try {
                // Check if the summary row already exists to decide on created_at timestamp manually
                $exists = DB::table('daily_attendance_summaries')
                    ->where('employee_id', $employeeId)
                    ->where('date', $targetDate)
                    ->exists();

                if ($exists) {
                    // Row exists: only update total time and updated_at
                    DB::table('daily_attendance_summaries')
                        ->where('employee_id', $employeeId)
                        ->where('date', $targetDate)
                        ->update([
                            'total_time_in_office' => $totalTime,
                            'updated_at'           => $now,
                        ]);
                } else {
                    // Row doesn't exist: insert clean values completely safely without DB::raw syntax overhead
                    DB::table('daily_attendance_summaries')->insert([
                        'employee_id'          => $employeeId,
                        'date'                 => $targetDate,
                        'total_time_in_office' => $totalTime,
                        'created_at'           => $now,
                        'updated_at'           => $now,
                    ]);
                }
            } catch (\Exception $rowException) {
                // Individual row safeguard: Log errors quietly to console output without stopping the cron loop
                $this->error("Skipped Employee {$employeeId} calculation due to processing errors. Error: {$rowException->getMessage()}");
            }
        }

        $this->info("Success! Attendance summaries successfully processed and synchronized for {$targetDate}.");
        return Command::SUCCESS;
    }
}