<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceService
{
    /**
     * Calculates total time in office.
     * 1. Sums finished blocks (IN -> OUT).
     * 2. For the final session:
     * - If OUT exists: Uses that time (Precise duration).
     * - If no OUT (Today): Uses 'Now'.
     * - If no OUT (Past): Uses 'End of Day' (Emergency fallback).
     */
    public function calculateDailyTotals($dateString, $employeeId = null)
    {
        $timezone = config('app.timezone', 'UTC');
        
        $query = DB::table('attendances')->whereDate('timestamp', $dateString);
        if ($employeeId !== null) {
            $query->where('employee_id', $employeeId);
        }
        $attendance = $query->get();
        
        $isToday = Carbon::now($timezone)->toDateString() === $dateString;
        $targetDateEnd = Carbon::parse($dateString, $timezone)->endOfDay();
        
        $grouped = $attendance->groupBy('employee_id');
        $officeTimes = [];

        foreach ($grouped as $empId => $logs) {
            $sortedLogs = $logs->sort(function ($a, $b) {
                if ($a->timestamp === $b->timestamp) return $a->id <=> $b->id;
                return strcmp($a->timestamp, $b->timestamp);
            })->values();
            
            $totalSecondsInOffice = 0;
            $currentBlockStart = null;
            $currentBlockEnd = null;

            foreach ($sortedLogs as $log) {
                $logTime = Carbon::createFromFormat('Y-m-d H:i:s', $log->timestamp, $timezone);
                $status = (int) $log->status1;

                if ($status === 0) { // IN
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
                } elseif ($status === 1) { // OUT
                    if ($currentBlockStart !== null) {
                        if ($currentBlockEnd === null) {
                            $currentBlockEnd = $logTime;
                        } else {
                            $outGapSeconds = $logTime->diffInSeconds($currentBlockEnd);
                            if ($outGapSeconds <= 30) { 
                                $currentBlockEnd = $logTime; // Extend exit
                            } else {
                                $totalSecondsInOffice += $currentBlockStart->diffInSeconds($currentBlockEnd);
                                $currentBlockStart = null;
                                $currentBlockEnd = null;
                            }
                        }
                    }
                }
            }

            if ($currentBlockStart !== null) {
                // If a valid OUT punch exists, use it. This ensures "First IN - Last OUT"
                if ($currentBlockEnd !== null) {
                    $totalSecondsInOffice += $currentBlockStart->diffInSeconds($currentBlockEnd);
                } else {
                    // No OUT punch: Use fallback logic
                    $endTime = $isToday ? Carbon::now($timezone) : $targetDateEnd;
                    $totalSecondsInOffice += $currentBlockStart->diffInSeconds($endTime);
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