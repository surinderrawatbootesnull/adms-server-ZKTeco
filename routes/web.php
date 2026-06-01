<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\iclockController;
use App\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| LOGIN ROUTES (NO AUTH)
|--------------------------------------------------------------------------
*/

Route::get('/login', [AuthController::class, 'showLogin']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/logout', [AuthController::class, 'logout']);


/*
|--------------------------------------------------------------------------
| PROTECTED DASHBOARD ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware([
    'verify.passport.token',
])->group(function () {

    Route::get('/', function () {
        return redirect()->route('devices.index');
    });

    Route::get(
        'devices',
        [DeviceController::class, 'Index']
    )->name('devices.index');

    Route::get(
        'devices-log',
        [DeviceController::class, 'DeviceLog']
    )->name('devices.DeviceLog');

    Route::get(
        'finger-log',
        [DeviceController::class, 'FingerLog']
    )->name('devices.FingerLog');

    Route::get(
        'attendance',
        [DeviceController::class, 'Attendance']
    )->name('devices.Attendance');
});


/*
|--------------------------------------------------------------------------
| DEVICE ROUTES (NO LOGIN)
|--------------------------------------------------------------------------
*/

Route::match(
    ['get', 'post'],
    '/iclock/cdata',
    [iclockController::class, 'handleCdata']
);

Route::get(
    '/iclock/cdata.aspx',
    [iclockController::class, 'handleCdata']
);

Route::post(
    '/iclock/cdata.aspx',
    [iclockController::class, 'handleCdata']
);

Route::get(
    '/iclock/getrequest',
    [iclockController::class, 'getrequest']
);

Route::get(
    '/iclock/getrequest.aspx',
    [iclockController::class, 'getrequest']
);

Route::post(
    '/iclock/devicecmd',
    [iclockController::class, 'devicecmd']
);


/*
|--------------------------------------------------------------------------
| TEST ROUTE
|--------------------------------------------------------------------------
*/

Route::get('/hello', function () {
    return "Router is working!";
});