<?php

use App\Http\Controllers\Api\V1\AttendanceDeviceController;
use Illuminate\Support\Facades\Route;

Route::apiResource('attendance-devices', AttendanceDeviceController::class)
    ->only(['index', 'store', 'update'])
    ->parameters(['attendance-devices' => 'attendance_device']);
Route::post('attendance-devices/{attendance_device}/token', [AttendanceDeviceController::class, 'regenerateToken'])
    ->name('attendance-devices.token');
