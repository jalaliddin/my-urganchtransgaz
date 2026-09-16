<?php

use App\Http\Controllers\Api\V1\ExamAttemptController;
use App\Http\Controllers\Api\V1\ExamController;
use App\Http\Controllers\Api\V1\ExamQuestionController;
use App\Http\Controllers\Api\V1\ExamResultController;
use Illuminate\Support\Facades\Route;

Route::prefix('exams')->name('exams.')->group(function () {
    Route::get('/', [ExamController::class, 'index'])->name('index');
    Route::post('/', [ExamController::class, 'store'])->name('store');
    Route::get('{exam}', [ExamController::class, 'show'])->name('show');
    Route::put('{exam}', [ExamController::class, 'update'])->name('update');

    Route::get('{exam}/questions', [ExamQuestionController::class, 'index'])->name('questions.index');
    Route::post('{exam}/questions', [ExamQuestionController::class, 'store'])->name('questions.store');
    Route::put('{exam}/questions/{question}', [ExamQuestionController::class, 'update'])->name('questions.update');
    Route::delete('{exam}/questions/{question}', [ExamQuestionController::class, 'destroy'])->name('questions.destroy');

    Route::post('{exam}/attempts', [ExamAttemptController::class, 'store'])->name('attempts.store');
    Route::post('{exam}/attempts/{attempt}/submit', [ExamAttemptController::class, 'submit'])->name('attempts.submit');
    Route::get('{exam}/attempts/{attempt}', [ExamAttemptController::class, 'show'])->name('attempts.show');

    Route::get('{exam}/results', [ExamResultController::class, 'index'])->name('results');
});
