<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class iclockController extends Controller
{
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
            $tot = 0;

            if (!empty($rawContent)) {
                $lines = preg_split('/\r\n|\r|\n/', $rawContent);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;

                    $data = explode("\t", $line);
                    if (count($data) < 2) continue;

                    try {
                        DB::table('attendances')->insert([
                            'sn'          => $sn,
                            'table'       => $table,
                            'stamp'       => $request->query('Stamp') ?? '',
                            'employee_id' => (int) trim($data[0]),
                            'timestamp'   => trim($data[1]),
                            'status1'     => isset($data[2]) && $data[2] !== '' ? (int)$data[2] : null,
                            'status2'     => isset($data[3]) && $data[3] !== '' ? (int)$data[3] : null,
                            'status3'     => isset($data[4]) && $data[4] !== '' ? (int)$data[4] : null,
                            'status4'     => isset($data[5]) && $data[5] !== '' ? (int)$data[5] : null,
                            'status5'     => isset($data[6]) && $data[6] !== '' ? (int)$data[6] : null,
                            'created_at'  => now(),
                            'updated_at'  => now(),
                        ]);
                        $tot++;
                    } catch (\Exception $e) {
                        Log::error("Attendance insert error: " . $e->getMessage());
                    }
                }
            }

            return response("OK: $tot\n", 200)->header('Content-Type', 'text/plain');
        }

        // GET — handshake response
        DB::table('devices')->updateOrInsert(
            ['no_sn' => $sn],
            ['online' => now()]
        );

        $resp = "GET OPTION FROM: $sn\r\n" .
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

        return response($resp, 200)->header('Content-Type', 'text/plain');
    }

    public function getrequest() {
        return response("OK", 200)->header('Content-Type', 'text/plain');
    }

    public function devicecmd() {
        return response("OK", 200)->header('Content-Type', 'text/plain');
    }
}