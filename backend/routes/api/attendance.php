<?php

use App\Http\Controllers\Api\V1\AttendanceController;
use Illuminate\Support\Facades\Route;

Route::prefix('attendance')->name('attendance.')->group(function () {
    Route::get('/', [AttendanceController::class, 'index'])->name('index');
    Route::post('/', [AttendanceController::class, 'store'])->name('store');
    Route::get('today', [AttendanceController::class, 'today'])->name('today');
    Route::get('report', [AttendanceController::class, 'report'])->name('report');
    Route::get('timesheet', [AttendanceController::class, 'timesheet'])->name('timesheet');
    Route::post('check-in', [AttendanceController::class, 'checkIn'])->name('check-in');
    Route::post('check-out', [AttendanceController::class, 'checkOut'])->name('check-out');
    Route::get('{attendanceRecord}', [AttendanceController::class, 'show'])->name('show');
    Route::put('{attendanceRecord}', [AttendanceController::class, 'update'])->name('update');
    Route::delete('{attendanceRecord}', [AttendanceController::class, 'destroy'])->name('destroy');
});
