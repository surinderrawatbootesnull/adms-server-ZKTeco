<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AttendanceController;

// PROTECTED API ROUTES
Route::middleware(['verify.passport.token'])->group(function () {
    Route::get('/attendance/{employeeId?}', [AttendanceController::class, 'getAttendance']);
    
    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
        return $request->user();
    });
});

// Secured eSSL Webhook Endpoint Group 
Route::prefix('webhooks')
    ->middleware([\App\Http\Middleware\VerifyInternalSecret::class])
    ->group(function () {
        // Pointing to the SAME controller method
        Route::get('/attendance/{employeeId?}', [AttendanceController::class, 'getAttendance']);
    });