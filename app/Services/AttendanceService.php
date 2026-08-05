<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceService
{
    /**
     * Calculates total time in office.
     * Logic: Deduplicates -> Sorts -> Processes Blocks -> Handles Gaps/Open Shifts.
     */
    public function calculateDailyTotals($dateString, $employeeId = null)
    {
        $timezone = config('app.timezone', 'UTC');
        
        // 1. QUERY: Explicitly selecting columns prevents stale 'total_time_in_office' from leaking
        $query = DB::table('attendances')
            ->select('id', 'sn', 'table', 'stamp', 'employee_id', 'timestamp', 'status1', 'created_at', 'updated_at')
            ->whereDate('timestamp', $dateString);
            
        if ($employeeId !== null) {
            $query->where('employee_id', $employeeId);
        }
        $attendance = $query->get();
        
        $isToday = Carbon::now($timezone)->toDateString() === $dateString;
        $grouped = $attendance->groupBy('employee_id');
        $officeTimes = [];

        foreach ($grouped as $empId => $logs) {
            // 2. DEDUPLICATION: Removes exact duplicates
            $uniqueLogs = $logs->unique(function ($item) {
                return $item->timestamp . '|' . $item->status1;
            });

            // 3. SORTING: Ensures chronological processing
            $sortedLogs = $uniqueLogs->sort(function ($a, $b) {
                if ($a->timestamp === $b->timestamp) return $a->id <=> $b->id;
                return strcmp($a->timestamp, $b->timestamp);
            })->values();
            
            $totalSecondsInOffice = 0;
            $currentBlockStart = null;
            $currentBlockEnd = null;
            $lastLogTime = null;

            // 4. CALCULATION LOOP
            foreach ($sortedLogs as $log) {
                $logTime = Carbon::createFromFormat('Y-m-d H:i:s', $log->timestamp, $timezone);
                $lastLogTime = $logTime; 
                $status = (int) $log->status1;

                if ($log->sn === config('app.outer_device_sn')) { // IN
                    if ($currentBlockStart === null) {
                        $currentBlockStart = $logTime;
                    } elseif ($currentBlockEnd !== null) {
                        $gapSeconds = $logTime->diffInSeconds($currentBlockEnd);
                        if ($gapSeconds <= 30) { 
                            $currentBlockEnd = null; 
                        } else {
                            $totalSecondsInOffice += $currentBlockStart->diffInSeconds($currentBlockEnd);
                            $currentBlockStart = $logTime;
                            $currentBlockEnd = null;
                        }
                    }
                } elseif ($log->sn === config('app.inner_device_sn')) { // OUT
                    if ($currentBlockStart !== null) {
                        if ($currentBlockEnd === null) {
                            $currentBlockEnd = $logTime;
                        } else {
                            $outGapSeconds = $logTime->diffInSeconds($currentBlockEnd);
                            if ($outGapSeconds <= 30) { 
                                $currentBlockEnd = $logTime;
                            } else {
                                $totalSecondsInOffice += $currentBlockStart->diffInSeconds($currentBlockEnd);
                                $currentBlockStart = null;
                                $currentBlockEnd = null;
                            }
                        }
                    }
                }
            }

            // 5. FALLBACK LOGIC
            if ($currentBlockStart !== null) {
                if ($currentBlockEnd !== null) {
                    $totalSecondsInOffice += $currentBlockStart->diffInSeconds($currentBlockEnd);
                } else {
                    // Today: Count to NOW. Past: Count to the last known action (or 0 if ambiguous)
                    $endTime = $isToday ? Carbon::now($timezone) : $lastLogTime;
                    
                    // Only add if it's today OR if we have a valid block to close
                    if ($isToday || ($lastLogTime && $currentBlockStart->lt($lastLogTime))) {
                        $totalSecondsInOffice += $currentBlockStart->diffInSeconds($endTime);
                    }
                }
            }

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