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
    protected $signature = 'attendance:calculate-daily';

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
        $this->info('Starting daily attendance processing calculations...');
        
        $timezone = env('APP_TIMEZONE', 'UTC');

        // Target today's date context using the dynamic timezone
        $targetDate = Carbon::today($timezone)->toDateString();

        try {
            // Run core calculation service engine
            $result = $this->attendanceService->calculateDailyTotals($targetDate);
            $calculatedTotals = $result['calculated_totals'] ?? [];
        } catch (\Exception $e) {
            $this->error("Critical Failure: The calculation engine crashed during initialization. Error: {$e->getMessage()}");
            return Command::FAILURE;
        }

        if (empty($calculatedTotals)) {
            $this->warn("No active employee logs parsed for today ({$targetDate}).");
            return Command::SUCCESS;
        }

        // Loop through calculations with individual row safeguards
        foreach ($calculatedTotals as $employeeId => $totalTime) {
            try {
                $currentTimeString = Carbon::now($timezone)->toDateTimeString();

                // Preserves creation timestamp
                DB::table('daily_attendance_summaries')->updateOrInsert(
                    [
                        'employee_id' => $employeeId,
                        'date'        => $targetDate,
                    ],
                    [
                        'total_time_in_office' => $totalTime,
                        'updated_at'           => Carbon::now($timezone),
                        'created_at'           => DB::raw("COALESCE(created_at, '{$currentTimeString}')"),
                    ]
                );
            } catch (\Exception $rowException) {
                // Individual row safeguard: Log errors quietly to console output without stopping the cron loop
                $this->error("Skipped Employee {$employeeId} calculation due to data corruption. Error: {$rowException->getMessage()}");
            }
        }

        $this->info('Success! Daily attendance summaries successfully processed and synchronized.');
        return Command::SUCCESS;
    }
}