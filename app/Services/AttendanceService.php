<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceService
{
    /**
     * Centralized math formula to calculate total time in office for a given date context.
     */
    public function calculateDailyTotals($dateString, $employeeId = null)
    {
        // 1. Get the 'APP_TIMEZONE' key from .env file
        $timezone = env('APP_TIMEZONE', 'UTC');

        // 2. Get the target date's logs from the database
        $query = DB::table('attendances')
            ->whereDate('timestamp', $dateString);

        if ($employeeId !== null) {
            $query->where('employee_id', $employeeId);
        }

        $attendance = $query->get();

        // 3. Group records by employee to process working durations
        $grouped = $attendance->groupBy('employee_id');
        $officeTimes = [];

        // Check if the requested date context matches today's local date
        $isToday = ($dateString === Carbon::today($timezone)->toDateString());
        $currentTimeInOffice = Carbon::now($timezone);

        foreach ($grouped as $empId => $logs) {
            
            // Sort logs chronologically to ensure pairing flow from morning to night
            $sortedLogs = $logs->sortBy('timestamp')->values();
            
            $totalSecondsInOffice = 0;
            $lastInTime = null;

            foreach ($sortedLogs as $log) {
                // Parse the log timestamp using your environment's timezone context
                $logTime = Carbon::parse($log->timestamp, $timezone);
                $status = (int) $log->status1; // 0 = IN machine, 1 = OUT machine

                if ($status === 0) {
                    // Employee scanned an "IN" machine. Lock onto the first scan.
                    if ($lastInTime === null) {
                        $lastInTime = $logTime;
                    }
                } 
                elseif ($status === 1) {
                    // Employee scanned an "OUT" machine. Pair it with the previous IN scan.
                    if ($lastInTime !== null) {
                        $totalSecondsInOffice += $lastInTime->diffInSeconds($logTime);
                        $lastInTime = null; // Clear anchor for the next pairing block
                    }
                }
            }

            // LIVE TICKING If the employee checked IN but never checked OUT,
            // and we are calculating for TODAY, track running time up to this exact second.
            if ($lastInTime !== null && $isToday) {
                if ($currentTimeInOffice->gt($lastInTime)) {
                    $totalSecondsInOffice += $lastInTime->diffInSeconds($currentTimeInOffice);
                }
            }

            // 4. Mathematical conversion from seconds into clean HH:MM:SS format
            $hours = floor($totalSecondsInOffice / 3600);
            $minutes = floor(($totalSecondsInOffice / 60) % 60);
            $seconds = $totalSecondsInOffice % 60;

            $officeTimes[$empId] = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        // Return both raw logs and calculated values back to the caller component
        return [
            'raw_logs' => $attendance,
            'calculated_totals' => $officeTimes
        ];
    }
}