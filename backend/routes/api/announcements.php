<?php

use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\RoleController;
use Illuminate\Support\Facades\Route;

Route::get('roles', [RoleController::class, 'index'])->name('roles.index');

Route::prefix('announcements')->name('announcements.')->group(function () {
    Route::get('/', [AnnouncementController::class, 'index'])->name('index');
    Route::post('/', [AnnouncementController::class, 'store'])->name('store');
    Route::get('{announcement}', [AnnouncementController::class, 'show'])->name('show');
    Route::put('{announcement}', [AnnouncementController::class, 'update'])->name('update');

    Route::post('{announcement}/publish', [AnnouncementController::class, 'publish'])->name('publish');
    Route::post('{announcement}/archive', [AnnouncementController::class, 'archive'])->name('archive');

    Route::post('{announcement}/image', [AnnouncementController::class, 'uploadImage'])->name('image.upload');
    Route::get('{announcement}/image', [AnnouncementController::class, 'image'])->name('image.show');
    Route::post('{announcement}/attachment', [AnnouncementController::class, 'uploadAttachment'])->name('attachment.upload');
    Route::get('{announcement}/attachment', [AnnouncementController::class, 'attachment'])->name('attachment.show');
});
