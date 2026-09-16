<?php

use App\Http\Controllers\Api\V1\BusinessTripController;
use Illuminate\Support\Facades\Route;

Route::prefix('business-trips')->name('business-trips.')->group(function () {
    Route::get('/', [BusinessTripController::class, 'index'])->name('index');
    Route::get('upcoming', [BusinessTripController::class, 'upcoming'])->name('upcoming');
    Route::post('/', [BusinessTripController::class, 'store'])->name('store');
    Route::get('{businessTrip}', [BusinessTripController::class, 'show'])->name('show');
    Route::put('{businessTrip}', [BusinessTripController::class, 'update'])->name('update');
    Route::post('{businessTrip}/cancel', [BusinessTripController::class, 'cancel'])->name('cancel');
});
