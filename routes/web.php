<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\iclockController;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Cache Reset Trigger (Temporary)
|--------------------------------------------------------------------------
| Loading any page on your site once with these lines active will flush 
| Hostinger's cached routing maps. Delete or comment them out afterward.
*/
try {
    Artisan::call('route:clear');
    Artisan::call('cache:clear');
} catch (\Exception $e) {
    // Fail silently if artisan commands are restricted on your host
}

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// 1. Dashboard Routes
Route::get('/', function () {
    return redirect()->route('devices.index');
});

Route::get('devices', [DeviceController::class, 'Index'])->name('devices.index');
Route::get('devices-log', [DeviceController::class, 'DeviceLog'])->name('devices.DeviceLog');
Route::get('finger-log', [DeviceController::class, 'FingerLog'])->name('devices.FingerLog');
Route::get('attendance', [DeviceController::class, 'Attendance'])->name('devices.Attendance');

// 2. The Universal Core ADMS Route
// Real machines send both Handshakes (GET) and Logs (POST) directly to /iclock/cdata
Route::match(['get', 'post'], '/iclock/cdata', [iclockController::class, 'handleCdata']);

// ADD THESE — accept the .aspx variant the device actually sends
Route::get('/iclock/cdata.aspx', [iclockController::class, 'handleCdata']);
Route::post('/iclock/cdata.aspx', [iclockController::class, 'handleCdata']);

// Also cover getrequest.aspx which appeared in your logs
Route::get('/iclock/getrequest.aspx', [iclockController::class, 'getrequest']);

// 3. Fallbacks and Command Routes required by the ADMS firmware
Route::get('/iclock/getrequest', [iclockController::class, 'getrequest']);
Route::post('/iclock/devicecmd', [iclockController::class, 'devicecmd']);

// 4. Verification Route
Route::get('/hello', function() {
    return "Router is working!";
});
