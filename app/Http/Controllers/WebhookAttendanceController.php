<?php

namespace App\Http\Controllers;

use App\Services\AttendanceService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class WebhookAttendanceController extends Controller
{
    protected $attendanceService;

    public function __construct(AttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    /**
     * Secured eSSL Webhook to fetch records matching production capabilities.
     * GET /api/webhooks/attendance/{employeeId?}
     */
    public function getAttendance(Request $request, $employeeId = null)
    {
        try {
            $timezone = config('app.timezone', 'UTC');
            $dateParam = $request->query('date'); 

            // --- SCENARIO 1: HISTORICAL DATA MODE (?date=all) ---
            if ($dateParam === 'all') {
                
                $query = DB::table('attendances');
                if ($employeeId !== null) {
                    $query->where('employee_id', $employeeId);
                }
                $attendance = $query->orderBy('timestamp', 'asc')->get();

                $summaryQuery = DB::table('daily_attendance_summaries');
                if ($employeeId !== null) {
                    $summaryQuery->where('employee_id', $employeeId);
                }
                
                $summaries = $summaryQuery->get()->keyBy(function ($item) {
                    return ((int) $item->employee_id) . '_' . $item->date;
                });

                $todayStr = Carbon::today($timezone)->toDateString();
                
                // Calculate today's totals ONCE to prevent N+1 query loop
                $todayLiveTotals = $this->attendanceService->calculateDailyTotals($todayStr, $employeeId)['calculated_totals'] ?? [];

                foreach ($attendance as $log) {
                    // FIX: Extract raw date string (YYYY-MM-DD) directly from text to prevent unexpected Carbon timezone shifting
                    $logDate = explode(' ', $log->timestamp)[0];
                    $empId = (int) $log->employee_id;
                    $lookupKey = $empId . '_' . $logDate;
                    
                    if ($logDate === $todayStr) {
                        $log->total_time_in_office = $todayLiveTotals[$empId] ?? '00:00:00';
                    } else {
                        $log->total_time_in_office = $summaries[$lookupKey]->total_time_in_office ?? '00:00:00';
                    }
                }

                return response()->json([
                    'success' => true,
                    'mode' => 'history',
                    'count' => $attendance->count(),
                    'data' => $attendance
                ], Response::HTTP_OK);
            }

            // --- SCENARIO 2: SINGLE DAY MODE (DEFAULT TODAY OR EXPLICIT DATE) ---
            if (!empty($dateParam)) {
                try {
                    $targetDate = Carbon::parse($dateParam, $timezone)->toDateString();
                    $mode = 'custom_date';
                } catch (\Exception $invalidDateException) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid date format provided. Please use YYYY-MM-DD or "all".',
                    ], Response::HTTP_BAD_REQUEST);
                }
            } else {
                $targetDate = Carbon::today($timezone)->toDateString();
                $mode = 'today';
            }
            
            $result = $this->attendanceService->calculateDailyTotals($targetDate, $employeeId);
            $attendance = $result['raw_logs'];
            $officeTimes = $result['calculated_totals'];

            foreach ($attendance as $log) {
                $empId = (int) $log->employee_id;
                $log->total_time_in_office = $officeTimes[$empId] ?? '00:00:00';
            }

            return response()->json([
                'success' => true,
                'mode' => $mode,
                'target_date' => $targetDate,
                'count' => $attendance->count(),
                'data' => $attendance
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error("Secure Webhook Execution Failure: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Database Error.',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}