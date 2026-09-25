<?php

use App\Http\Controllers\Api\V1\EmployeeAbsenceController;
use Illuminate\Support\Facades\Route;

Route::get('employees/{employee}/leave-balance', [EmployeeAbsenceController::class, 'balance'])->name('employees.leave-balance');

Route::prefix('absences')->name('absences.')->group(function () {
    Route::get('/', [EmployeeAbsenceController::class, 'index'])->name('index');
    Route::post('/', [EmployeeAbsenceController::class, 'store'])->name('store');
    Route::get('{absence}', [EmployeeAbsenceController::class, 'show'])->name('show');
    Route::put('{absence}', [EmployeeAbsenceController::class, 'update'])->name('update');
    Route::post('{absence}/cancel', [EmployeeAbsenceController::class, 'cancel'])->name('cancel');
    Route::get('{absence}/download', [EmployeeAbsenceController::class, 'download'])->name('download');
});
