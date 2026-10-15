<?php

use App\Http\Controllers\Api\V1\AttendanceDeviceController;
use App\Http\Controllers\Api\V1\AttendanceDevicePeopleController;
use Illuminate\Support\Facades\Route;

Route::get('attendance-devices/people', [AttendanceDevicePeopleController::class, 'index'])->name('attendance-devices.people');
Route::post('attendance-devices/people/link', [AttendanceDevicePeopleController::class, 'link'])->name('attendance-devices.people.link');
Route::post('attendance-devices/people/unlink', [AttendanceDevicePeopleController::class, 'unlink'])->name('attendance-devices.people.unlink');

Route::apiResource('attendance-devices', AttendanceDeviceController::class)
    ->only(['index', 'store', 'update'])
    ->parameters(['attendance-devices' => 'attendance_device']);
Route::post('attendance-devices/{attendance_device}/token', [AttendanceDeviceController::class, 'regenerateToken'])
    ->name('attendance-devices.token');
