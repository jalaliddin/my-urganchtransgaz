<?php

use App\Http\Controllers\Api\V1\SettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('settings')->name('settings.')->group(function () {
    Route::get('/', [SettingsController::class, 'index'])->name('index');
    Route::put('/', [SettingsController::class, 'update'])->name('update');
    Route::post('logo', [SettingsController::class, 'uploadLogo'])->name('logo.upload');
    Route::get('logo', [SettingsController::class, 'logo'])->name('logo.show');
});
