<?php

use App\Http\Controllers\Api\V1\LeaveRequestController;
use Illuminate\Support\Facades\Route;

Route::prefix('leave-requests')->name('leave-requests.')->group(function () {
    Route::get('/', [LeaveRequestController::class, 'index'])->name('index');
    Route::post('/', [LeaveRequestController::class, 'store'])->name('store');
    Route::get('{leaveRequest}', [LeaveRequestController::class, 'show'])->name('show');

    Route::post('{leaveRequest}/department-approve', [LeaveRequestController::class, 'departmentApprove'])->name('department-approve');
    Route::post('{leaveRequest}/department-reject', [LeaveRequestController::class, 'departmentReject'])->name('department-reject');
    Route::post('{leaveRequest}/approve', [LeaveRequestController::class, 'approve'])->name('approve');
    Route::post('{leaveRequest}/reject', [LeaveRequestController::class, 'reject'])->name('reject');
    Route::post('{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel'])->name('cancel');
});
