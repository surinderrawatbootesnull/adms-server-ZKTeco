<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AttendanceService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RebuildAttendanceSummaries extends Command
{
    protected $signature = 'attendance:rebuild-summaries {--force : Force complete recalculation of all records, bypassing cache checks}';
    protected $description = 'Optimized script to rebuild total_time_in_office summaries for all records (historical and current) using chunking and live progress tracking';

    protected $attendanceService;

    public function __construct(AttendanceService $attendanceService)
    {
        parent::__construct();
        $this->attendanceService = $attendanceService;
    }

    public function handle()
    {
        $isForced = $this->option('force');
        
        if ($isForced) {
            $this->warn('Running in FORCE mode. All existing summaries will be overwritten and fixed.');
        } else {
            $this->info('Running in SMART mode. Missing employee data gaps will be filled automatically.');
        }
        
        $timezone = config('app.timezone', 'UTC');

        // 1. Fetch clean unique dates as a raw array to prevent memory leaks on large datasets
        $distinctDates = DB::table('attendances')
            ->select(DB::raw('DATE(timestamp) as log_date'))
            ->distinct()
            ->orderBy('log_date', 'asc')
            ->pluck('log_date')
            ->toArray();

        $totalDays = count($distinctDates);

        if ($totalDays === 0) {
            $this->error('No raw attendance data found to process.');
            return;
        }

        // 2. Fetch already-calculated summaries mapped by BOTH date and employee_id 
        $existingSummaries = [];
        if (!$isForced) {
            $summaries = DB::table('daily_attendance_summaries')
                ->select('date', 'employee_id')
                ->get();

            foreach ($summaries as $row) {
                // Creates a lightning fast unique composite look-up array key
                $existingSummaries[$row->date . '_' . $row->employee_id] = true;
            }
            unset($summaries); // Free memory immediately
        }

        $this->info("Found {$totalDays} total unique date logs to parse.");
        
        $progressBar = $this->output->createProgressBar($totalDays);
        $progressBar->start();

        // 3. Safe native array chunking to guarantee absolute RAM stability
        $chunks = array_chunk($distinctDates, 50);
        $failedDates = []; 

        foreach ($chunks as $dateBatch) {
            foreach ($dateBatch as $dateString) {
                try {
                    // Calculate accurate working times using your core central service engine logic
                    $result = $this->attendanceService->calculateDailyTotals($dateString);
                    $calculatedTotals = $result['calculated_totals'] ?? [];

                    // 4. Upsert calculated rows safely into the dashboard histories table
                    foreach ($calculatedTotals as $employeeId => $totalTime) {
                        
                        //Only skip if this specific employee already has data for this date
                        if (!$isForced && isset($existingSummaries[$dateString . '_' . $employeeId])) {
                            continue;
                        }

                        $currentTimeString = Carbon::now($timezone)->toDateTimeString();

                        DB::table('daily_attendance_summaries')->updateOrInsert(
                            [
                                'employee_id' => $employeeId,
                                'date'        => $dateString,
                            ],
                            [
                                'total_time_in_office' => $totalTime,
                                'updated_at'           => Carbon::now($timezone),
                                'created_at'           => DB::raw("COALESCE(created_at, '{$currentTimeString}')"),
                            ]
                        );
                    }
                } catch (\Exception $e) {
                    $failedDates[] = "Date: {$dateString} | Error: {$e->getMessage()}";
                }

                $progressBar->advance();
            }
            unset($dateBatch);
        }

        $progressBar->finish();
        $this->newLine(2);

        if (!empty($failedDates)) {
            $this->warn('Synchronization completed with some skipped errors:');
            foreach ($failedDates as $errorLog) {
                $this->error($errorLog);
            }
            $this->newLine();
        }

        $this->info('Success! Summary records have been completely synchronized.');
    }
}