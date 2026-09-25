<?php

use App\Http\Controllers\Api\V1\IssueCommentController;
use App\Http\Controllers\Api\V1\IssueController;
use App\Http\Controllers\Api\V1\IssueReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('issues')->name('issues.')->group(function () {
    Route::get('/', [IssueController::class, 'index'])->name('index');
    Route::post('/', [IssueController::class, 'store'])->name('store');
    Route::get('options', [IssueController::class, 'options'])->name('options');
    Route::get('report', IssueReportController::class)->name('report');
    Route::get('executor-candidates', [IssueController::class, 'executorCandidates'])->name('executor-candidates');
    Route::get('{issue}', [IssueController::class, 'show'])->name('show');
    Route::post('{issue}/resolve', [IssueController::class, 'resolve'])->name('resolve');

    Route::post('{issue}/comments', [IssueCommentController::class, 'store'])->name('comments.store');
});
