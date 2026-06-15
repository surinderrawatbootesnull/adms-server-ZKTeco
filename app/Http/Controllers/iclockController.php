<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\AttendanceService;
use Carbon\Carbon;

class iclockController extends Controller
{
    protected $attendanceService;

    public function __construct(AttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    public function handleCdata(Request $request)
    {
        $secret = 'c63b75c1207fc357379492a8d5a0bb7af92604c5791b39e5cb7eff2e46fff1fa';
        if ($request->header('X-ADMS-Token') !== $secret) {
            return response('Forbidden', 403);
        }

        $sn = $request->query('SN') ?? 'UNKNOWN';
        $table = $request->query('table') ?? '';

        Log::info("ADMS request - Method: {$request->method()}, SN: {$sn}, Table: {$table}");

        if ($request->isMethod('post')) {
            $rawContent = $request->getContent();
            $total = 0;

            if (!empty($rawContent)) {

                $lines = preg_split('/\r\n|\r|\n/', $rawContent);

                foreach ($lines as $line) {

                    $line = trim($line);

                    if (empty($line)) {
                        continue;
                    }

                    $data = explode("\t", $line);

                    if (count($data) < 2) {
                        continue;
                    }

                    try {

                        $employeeId = (int) trim($data[0]);
                        $attendanceTimestamp = trim($data[1]);

                        $status1 = isset($data[2]) && $data[2] !== '' ? (int)$data[2] : null;
                        $status2 = isset($data[3]) && $data[3] !== '' ? (int)$data[3] : null;
                        $status3 = isset($data[4]) && $data[4] !== '' ? (int)$data[4] : null;
                        $status4 = isset($data[5]) && $data[5] !== '' ? (int)$data[5] : null;
                        $status5 = isset($data[6]) && $data[6] !== '' ? (int)$data[6] : null;

                        // add incoming log entry directly to local database
                        $attendanceId = DB::table('attendances')->insertGetId([
                            'sn'          => $sn,
                            'table'       => $table,
                            'stamp'       => $request->query('Stamp') ?? '',
                            'employee_id' => $employeeId,
                            'timestamp'   => $attendanceTimestamp,
                            'status1'     => $status1,
                            'status2'     => $status2,
                            'status3'     => $status3,
                            'status4'     => $status4,
                            'status5'     => $status5,
                            'created_at'  => now(),
                            'updated_at'  => now(),
                        ]);

                        // Extract targeted date string context (YYYY-MM-DD) for calculation engine
                        $punchDate = Carbon::parse($attendanceTimestamp)->toDateString();

                        // Compute working metrics including the newly appended raw punch log row
                        $calculationResult = $this->attendanceService->calculateDailyTotals($punchDate, $employeeId);
                        
                        // Extract specific formatted time string or default to zero balance baseline
                        $totalTimeInOffice = $calculationResult['calculated_totals'][$employeeId] ?? '00:00:00';

                        // Build comprehensive sync webhook packet payload structure
                        $payload = [
                            [
                                'id'                   => $attendanceId,
                                'sn'                   => $sn,
                                'employee_id'          => $employeeId,
                                'timestamp'            => $attendanceTimestamp,
                                'status_1'             => $status1,
                                'status_2'             => $status2,
                                'status_3'             => $status3,
                                'status_4'             => $status4,
                                'status_5'             => $status5,
                                'total_time_in_office' => $totalTimeInOffice
                            ]
                        ];

                        Log::info('Sending ESSL webhook with calculated totals', [
                            'url'     => env('ESSL_WEBHOOK_URL'),
                            'payload' => $payload,
                        ]);

                        try {
                            // Use withOptions to directly pass raw lowercased headers bypass normalizer
                            $response = Http::timeout(15)
                                ->acceptJson()
                                ->withOptions([
                                    'headers' => [
                                        'content-type'      => 'application/json',
                                        'cookie'            => 'hr_auth_token=' . env('HR_AUTH_TOKEN'),
                                        'x-internal-secret' => env('HR_INTERNAL_SECRET'),
                                    ]
                                ])
                                ->post(
                                    env('ESSL_WEBHOOK_URL'),
                                    $payload
                                );

                            Log::info('Attendance webhook response', [
                                'attendance_id' => $attendanceId,
                                'status'        => $response->status(),
                                'body'          => $response->body(),
                            ]);

                        } catch (\Exception $e) {

                            Log::error('Attendance webhook failed', [
                                'attendance_id' => $attendanceId,
                                'error'         => $e->getMessage(),
                            ]);
                        }

                        $total++;

                    } catch (\Exception $e) {

                        Log::error('Attendance insert error', [
                            'line'  => $line,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            return response("OK: {$total}\n", 200)
                ->header('Content-Type', 'text/plain');
        }

        DB::table('devices')->updateOrInsert(
            ['no_sn' => $sn],
            ['online' => now()]
        );

        $response =
            "GET OPTION FROM: {$sn}\r\n" .
            "Stamp=9999\r\n" .
            "OpStamp=" . time() . "\r\n" .
            "ErrorDelay=60\r\n" .
            "Delay=30\r\n" .
            "ResLogDay=18250\r\n" .
            "ResLogDelCount=10000\r\n" .
            "ResLogCount=50000\r\n" .
            "TransTimes=00:00;23:59\r\n" .
            "TransInterval=1\r\n" .
            "TransFlag=1111000000\r\n" .
            "Realtime=1\r\n" .
            "Encrypt=0\r\n";

        return response($response, 200)
            ->header('Content-Type', 'text/plain');
    }


    public function getrequest(Request $request)
    {
        return response('OK', 200)
            ->header('Content-Type', 'text/plain');
    }

    public function devicecmd(Request $request)
    {
        return response('OK', 200)
            ->header('Content-Type', 'text/plain');
    }
}