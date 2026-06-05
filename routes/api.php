<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AttendanceController;

// PROTECTED API ROUTES
Route::middleware([
    'verify.passport.token',
])->group(function () {
    
    // Made employeeId optional using '?'
    Route::get('/attendance/{employeeId?}', [AttendanceController::class, 'getAttendance']);

    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
        return $request->user();
    });
});
