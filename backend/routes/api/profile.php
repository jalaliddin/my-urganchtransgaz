<?php

use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProfilePhotoController;
use Illuminate\Support\Facades\Route;

Route::prefix('profile')->name('profile.')->group(function () {
    Route::get('/', [ProfileController::class, 'show'])->name('show');
    Route::put('/', [ProfileController::class, 'update'])->name('update');
    Route::get('completion', [ProfileController::class, 'completion'])->name('completion');
    Route::post('photo', [ProfilePhotoController::class, 'store'])->name('photo');
});
