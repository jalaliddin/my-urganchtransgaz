<?php

use App\Http\Controllers\Api\V1\ChangeRequestController;
use Illuminate\Support\Facades\Route;

Route::prefix('change-requests')->name('change-requests.')->group(function () {
    Route::get('/', [ChangeRequestController::class, 'index'])->name('index');
    Route::get('{changeRequest}', [ChangeRequestController::class, 'show'])->name('show');
    Route::post('{changeRequest}/approve', [ChangeRequestController::class, 'approve'])->name('approve');
    Route::post('{changeRequest}/reject', [ChangeRequestController::class, 'reject'])->name('reject');
});
