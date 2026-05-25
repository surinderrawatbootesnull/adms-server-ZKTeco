<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class iclockController extends Controller
{
    public function handleCdata(Request $request)
    {
        $method = $request->method();
        $sn = $request->query('SN') ?? 'UNKNOWN';
        $rawContent = file_get_contents('php://input');

        // This will let us see if the machine successfully authenticates
        Log::info("Hardware Touchpoint - Method: {$method}, SN: {$sn}");

        // 1. PROCESS INCOMING PUNCTURE PAYLOAD
        if (!empty($rawContent)) {
            $lines = explode("\n", $rawContent);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;

                $data = explode("\t", $line);
                if (count($data) >= 2) {
                    $userId    = trim($data[0]); 
                    $timestamp = trim($data[1]); 
                    
                    try {
                        DB::table('device_logs')->insertOrIgnore([
                            'serial_number' => $sn,
                            'user_id'       => $userId,
                            'punch_time'    => $timestamp,
                            'created_at'    => now(),
                            'updated_at'    => now()
                        ]);
                    } catch (\Exception $e) {
                        Log::error("Database Write Error: " . $e->getMessage());
                    }
                }
            }
            return response("GBK\nOK\n", 200)->header('Content-Type', 'text/plain');
        }

        // 2. THE HOSTINGER BYPASS HANDSHAKE
        // These specific ADMS firmware flags instruct the device to format its internal 
        // network packets to match Hostinger's exact domain routing rules.
        $resp = "GET OPTION FROM: $sn\n" .
                "Registry=1\n" .
                "Delay=10\n" .
                "ErrorDelay=30\n" .
                "TransTimes=00:00;23:59\n" .
                "TransInterval=1\n" .
                "Realtime=1\n" .
                "CustomHost=essl.bootesnull.in\n" . // Forces the device to inject the Host header
                "HTTPHeaders=Host: essl.bootesnull.in\n" . // Appends explicitly to the packet wrapper
                "OK\n";

        return response($resp, 200)->header('Content-Type', 'text/plain');
    }

    public function getrequest(Request $request) {
        return response("OK", 200)->header('Content-Type', 'text/plain');
    }

    public function devicecmd(Request $request) {
        return response("OK", 200)->header('Content-Type', 'text/plain');
    }
}