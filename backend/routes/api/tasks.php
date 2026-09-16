<?php

use App\Http\Controllers\Api\V1\TaskAttachmentController;
use App\Http\Controllers\Api\V1\TaskCommentController;
use App\Http\Controllers\Api\V1\TaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('tasks')->name('tasks.')->group(function () {
    Route::get('/', [TaskController::class, 'index'])->name('index');
    Route::post('/', [TaskController::class, 'store'])->name('store');
    Route::get('{task}', [TaskController::class, 'show'])->name('show');
    Route::put('{task}', [TaskController::class, 'update'])->name('update');
    Route::patch('{task}/progress', [TaskController::class, 'updateProgress'])->name('progress');
    Route::post('{task}/complete', [TaskController::class, 'complete'])->name('complete');
    Route::post('{task}/approve', [TaskController::class, 'approve'])->name('approve');
    Route::post('{task}/reopen', [TaskController::class, 'reopen'])->name('reopen');
    Route::post('{task}/cancel', [TaskController::class, 'cancel'])->name('cancel');

    Route::post('{task}/comments', [TaskCommentController::class, 'store'])->name('comments.store');
    Route::delete('{task}/comments/{comment}', [TaskCommentController::class, 'destroy'])->name('comments.destroy');

    Route::post('{task}/attachments', [TaskAttachmentController::class, 'store'])->name('attachments.store');
    Route::get('{task}/attachments/{attachment}/download', [TaskAttachmentController::class, 'download'])->name('attachments.download');
    Route::delete('{task}/attachments/{attachment}', [TaskAttachmentController::class, 'destroy'])->name('attachments.destroy');
});
