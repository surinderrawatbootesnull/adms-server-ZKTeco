<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AttendanceService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CalculateDailyAttendance extends Command
{
    protected $signature = 'attendance:calculate-daily';
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
        
        // Get 'APP_TIMEZONE' key from .env file
        $timezone = env('APP_TIMEZONE', 'UTC');

        // Target today's date context using the dynamic timezone
        $targetDate = Carbon::today($timezone)->toDateString();

        // Run calculation service
        $result = $this->attendanceService->calculateDailyTotals($targetDate);
        $calculatedTotals = $result['calculated_totals'];

        foreach ($calculatedTotals as $employeeId => $totalTime) {
            DB::table('daily_attendance_summaries')->updateOrInsert(
                [
                    'employee_id' => $employeeId,
                    'date'        => $targetDate,
                ],
                [
                    'total_time_in_office' => $totalTime,
                    'updated_at'           => Carbon::now($timezone),
                    'created_at'           => Carbon::now($timezone),
                ]
            );
        }

        $this->info('Daily attendance summaries successfully stored!');
    }
}