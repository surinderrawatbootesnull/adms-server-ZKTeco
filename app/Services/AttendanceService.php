<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceService
{
    /**
     * Calculates total time in office.
     * 1. Fetches raw data.
     * 2. Sanitizes data (Deduplication).
     * 3. Sorts by time.
     * 4. Calculates blocks, handles gaps (30s), and handles missing OUT punches.
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
            // --- 1. DEDUPLICATION LAYER ---
            // Before processing, remove exact duplicates (same time AND same status)
            // This prevents noise from confusing the calculation loop.
            $uniqueLogs = $logs->unique(function ($item) {
                return $item->timestamp . '|' . $item->status1;
            });

            // --- 2. SORTING LAYER ---
            $sortedLogs = $uniqueLogs->sort(function ($a, $b) {
                if ($a->timestamp === $b->timestamp) return $a->id <=> $b->id;
                return strcmp($a->timestamp, $b->timestamp);
            })->values();
            
            $totalSecondsInOffice = 0;
            $currentBlockStart = null;
            $currentBlockEnd = null;

            // --- 3. CALCULATION LOOP ---
            foreach ($sortedLogs as $log) {
                $logTime = Carbon::createFromFormat('Y-m-d H:i:s', $log->timestamp, $timezone);
                $status = (int) $log->status1;

                if ($status === 0) { // IN
                    if ($currentBlockStart === null) {
                        $currentBlockStart = $logTime;
                    } elseif ($currentBlockEnd !== null) {
                        $gapSeconds = $logTime->diffInSeconds($currentBlockEnd);
                        if ($gapSeconds <= 30) { 
                            // Re-entering quickly, ignore gap
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

            // --- 4. FALLBACK LOGIC ---
            if ($currentBlockStart !== null) {
                if ($currentBlockEnd !== null) {
                    $totalSecondsInOffice += $currentBlockStart->diffInSeconds($currentBlockEnd);
                } else {
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