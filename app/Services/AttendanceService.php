<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceService
{
    /**
     * Centralized math formula to calculate total time in office for a given date context.
     * * @param string $dateString
     * @param int|null $employeeId
     * @return array
     */
    public function calculateDailyTotals($dateString, $employeeId = null)
    {
        // Safe configuration lookup (reads from compiled cache in production)
        $timezone = config('app.timezone', 'UTC');

        // Fetch logs strictly belonging to the target calendar day
        $query = DB::table('attendances')
            ->whereDate('timestamp', $dateString);

        if ($employeeId !== null) {
            $query->where('employee_id', $employeeId);
        }

        $attendance = $query->get();
        $grouped = $attendance->groupBy('employee_id');
        $officeTimes = [];

        $isToday = ($dateString === Carbon::today($timezone)->toDateString());
        $targetDateEnd = Carbon::parse($dateString, $timezone)->endOfDay();
        
        // Live ticking runs up to 'now' if checking today; otherwise caps at midnight of that date
        $currentTimeInOffice = $isToday ? Carbon::now($timezone) : $targetDateEnd;

        foreach ($grouped as $empId => $logs) {
            
            // Sort by timestamp, using auto-increment ID as a reliable tie-breaker
            $sortedLogs = $logs->sort(function ($a, $b) {
                if ($a->timestamp === $b->timestamp) {
                    return $a->id <=> $b->id; 
                }
                return strcmp($a->timestamp, $b->timestamp);
            })->values();
            
            $totalSecondsInOffice = 0;
            $lastInTime = null;
            $lastProcessedTimestamp = null;

            foreach ($sortedLogs as $log) {
                $logTime = Carbon::createFromFormat('Y-m-d H:i:s', $log->timestamp, $timezone);
                $status = (int) $log->status1; // 0 = IN machine, 1 = OUT machine

                // Ignores duplicate scans from the biometric machine within 60 seconds
                if ($lastProcessedTimestamp && $logTime->diffInSeconds($lastProcessedTimestamp) < 60) {
                    continue; 
                }
                $lastProcessedTimestamp = $logTime;

                if ($status === 0) {
                    // Lock onto the first IN scan of a pairing block
                    if ($lastInTime === null) {
                        $lastInTime = $logTime;
                    }
                } 
                elseif ($status === 1) {
                    // Pair with the preceding IN scan
                    if ($lastInTime !== null) {
                        $totalSecondsInOffice += $lastInTime->diffInSeconds($logTime);
                        $lastInTime = null; // Reset anchor for next block
                    }
                }
            }

            // --- MISSED OUT-PUNCH & LIVE TICKING SAFETY CATCH ---
            if ($lastInTime !== null) {
                $lastInTime->setTimezone($timezone);
                
                if ($currentTimeInOffice->greaterThan($lastInTime)) {
                    $totalSecondsInOffice += $lastInTime->diffInSeconds($currentTimeInOffice);
                }
            }

            // Convert total accumulated seconds into standard HH:MM:SS format
            $hours = floor($totalSecondsInOffice / 3600);
            $minutes = floor(($totalSecondsInOffice / 60) % 60);
            $seconds = $totalSecondsInOffice % 60;

            $officeTimes[$empId] = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return [
            'raw_logs' => $attendance,
            'calculated_totals' => $officeTimes
        ];
    }
}