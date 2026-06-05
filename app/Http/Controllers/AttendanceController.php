<?php

namespace App\Http\Controllers;

use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    protected $attendanceService;

    public function __construct(AttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    /**
     * Fetch attendance records with precise calculations.
     * Supports optional URL parameter matching for employeeId.
     * Supports optional query parameter filtering for custom dates.
     */
    public function getAttendance(Request $request, $employeeId = null)
    {
        try {
            // 1. Get 'APP_TIMEZONE' key from .env file
            $timezone = env('APP_TIMEZONE', 'UTC');

            // 2. Read the optional 'date' parameter from the request query string (e.g., ?date=2026-06-04)
            // If it is missing or empty, it automatically defaults to today's date context.
            $targetDate = $request->query('date');

            if (empty($targetDate)) {
                $targetDate = Carbon::today($timezone)->toDateString();
            } else {
                // Sanitize and validate format string safety to ensure it is a valid date
                try {
                    $targetDate = Carbon::parse($targetDate, $timezone)->toDateString();
                } catch (\Exception $invalidDateException) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid date format provided. Please use YYYY-MM-DD.',
                    ], 400);
                }
            }
            
            // 3. Call the shared central calculation service using our targeted date
            $result = $this->attendanceService->calculateDailyTotals($targetDate, $employeeId);
            
            $attendance = $result['raw_logs'];
            $officeTimes = $result['calculated_totals'];

            // 4. Inject the calculated totals back into each raw log row item
            foreach ($attendance as $log) {
                $log->total_time_in_office = $officeTimes[$log->employee_id] ?? '00:00:00';
            }

            return response()->json([
                'success' => true,
                'target_date' => $targetDate, // Added to response metadata for clean API debugging
                'count' => $attendance->count(),
                'data' => $attendance
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database Error.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}