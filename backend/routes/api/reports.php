<?php

use App\Http\Controllers\Api\V1\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('reports/overview', [ReportController::class, 'overview'])->name('reports.overview');
