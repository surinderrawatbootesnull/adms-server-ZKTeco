<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceService
{
    /**
     * Calculates total time in office.
     * Logic: 
     * 1. Sorts logs chronologically.
     * 2. Ignores double-punches (hardware noise < 30s).
     * 3. Protects the initial IN punch from duplicate INs.
     * 4. Auto-closes missing OUT punches at 23:59:59.
     */
    public function calculateDailyTotals($dateString, $employeeId = null)
    {
        $timezone = config('app.timezone', 'UTC');
        
        // 1. Fetch raw logs
        $query = DB::table('attendances')->whereDate('timestamp', $dateString);
        if ($employeeId !== null) {
            $query->where('employee_id', $employeeId);
        }
        $attendance = $query->get();
        
        // 2. Group by employee to process individually
        $grouped = $attendance->groupBy('employee_id');
        $officeTimes = [];
        
        // Define "end of day" for unclosed shifts
        $targetDateEnd = Carbon::parse($dateString, $timezone)->endOfDay();

        foreach ($grouped as $empId => $logs) {
            
            // 3. Sorting: Time first, then database ID as a tie-breaker
            $sortedLogs = $logs->sort(function ($a, $b) {
                if ($a->timestamp === $b->timestamp) {
                    return $a->id <=> $b->id; 
                }
                return strcmp($a->timestamp, $b->timestamp);
            })->values();
            
            $totalSecondsInOffice = 0;
            $currentBlockStart = null;
            $currentBlockEnd = null;

            foreach ($sortedLogs as $log) {
                $logTime = Carbon::createFromFormat('Y-m-d H:i:s', $log->timestamp, $timezone);
                $status = (int) $log->status1; // 0 = IN, 1 = OUT

                if ($status === 0) { // IN
                    if ($currentBlockStart === null) {
                        $currentBlockStart = $logTime;
                    } elseif ($currentBlockEnd !== null) {
                        // Logic: IN after an OUT sequence
                        $gapSeconds = $logTime->diffInSeconds($currentBlockEnd);
                        
                        if ($gapSeconds <= 30) { 
                            // Hardware noise: Ignore the IN, keep the exit block alive
                            $currentBlockEnd = null;
                        } else {
                            // Genuine return: Save finished block, start new IN
                            $totalSecondsInOffice += $currentBlockStart->diffInSeconds($currentBlockEnd);
                            $currentBlockStart = $logTime;
                            $currentBlockEnd = null;
                        }
                    }
                    // Else: Consecutive IN punch. We ignore it to protect the original start time.
                    
                } elseif ($status === 1) { // OUT
                    if ($currentBlockStart !== null) {
                        if ($currentBlockEnd === null) {
                            $currentBlockEnd = $logTime;
                        } else {
                            // Logic: Consecutive OUT punch (double swipe)
                            $outGapSeconds = $logTime->diffInSeconds($currentBlockEnd);
                            
                            if ($outGapSeconds <= 30) { 
                                // Hardware noise: Extend the exit time to the latest punch
                                $currentBlockEnd = $logTime;
                            } else {
                                // Real gap: This is a new exit? Close the block.
                                $totalSecondsInOffice += $currentBlockStart->diffInSeconds($currentBlockEnd);
                                $currentBlockStart = null;
                                $currentBlockEnd = null;
                            }
                        }
                    }
                    // Else: Rogue OUT punch (no IN punch), ignore it.
                }
            }

            // 4. Add any remaining open block // if Employees forgot to punch out, we consider the end of the day as the exit time.
            if ($currentBlockStart !== null) {
                $endTime = ($currentBlockEnd !== null) ? $currentBlockEnd : $targetDateEnd;
                $totalSecondsInOffice += $currentBlockStart->diffInSeconds($endTime);
            }

            // 5. Convert to HH:MM:SS
            $hours = floor($totalSecondsInOffice / 3600);
            $minutes = floor(($totalSecondsInOffice / 60) % 60);
            $seconds = $totalSecondsInOffice % 60;

            $officeTimes[$empId] = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        // 6. Return strictly in the format the Controller and Commands expect
        return [
            'raw_logs' => $attendance,
            'calculated_totals' => $officeTimes
        ];
    }
}