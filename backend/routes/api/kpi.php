<?php

use App\Http\Controllers\Api\V1\KpiController;
use App\Http\Controllers\Api\V1\KpiIndicatorController;
use App\Http\Controllers\Api\V1\KpiPeriodController;
use App\Http\Controllers\Api\V1\KpiTemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('kpi-templates')->name('kpi-templates.')->group(function () {
    Route::get('/', [KpiTemplateController::class, 'index'])->name('index');
    Route::post('/', [KpiTemplateController::class, 'store'])->name('store');
    Route::get('{kpiTemplate}', [KpiTemplateController::class, 'show'])->name('show');
    Route::put('{kpiTemplate}', [KpiTemplateController::class, 'update'])->name('update');

    Route::get('{kpiTemplate}/indicators', [KpiIndicatorController::class, 'index'])->name('indicators.index');
    Route::post('{kpiTemplate}/indicators', [KpiIndicatorController::class, 'store'])->name('indicators.store');
    Route::put('{kpiTemplate}/indicators/{indicator}', [KpiIndicatorController::class, 'update'])->name('indicators.update');
});

Route::prefix('kpi')->name('kpi.')->group(function () {
    Route::get('periods', [KpiPeriodController::class, 'index'])->name('periods.index');
    Route::post('periods', [KpiPeriodController::class, 'store'])->name('periods.store');
    Route::put('periods/{kpiPeriod}', [KpiPeriodController::class, 'update'])->name('periods.update');

    Route::get('my', [KpiController::class, 'my'])->name('my');
    Route::get('report', [KpiController::class, 'report'])->name('report');
    Route::post('generate', [KpiController::class, 'generate'])->name('generate');

    Route::get('/', [KpiController::class, 'index'])->name('index');
    Route::post('/', [KpiController::class, 'store'])->name('store');
    Route::put('{employeeKpi}', [KpiController::class, 'update'])->name('update');
    Route::post('{employeeKpi}/approve', [KpiController::class, 'approve'])->name('approve');
});
