<?php

use App\Http\Controllers\Api\V1\DocumentTypeController;
use App\Http\Controllers\Api\V1\EmployeeDocumentController;
use Illuminate\Support\Facades\Route;

Route::get('document-types', [DocumentTypeController::class, 'index'])->name('document-types.index');

Route::prefix('documents')->name('documents.')->group(function () {
    Route::get('/', [EmployeeDocumentController::class, 'index'])->name('index');
    Route::post('/', [EmployeeDocumentController::class, 'store'])->name('store');
    Route::get('{employeeDocument}', [EmployeeDocumentController::class, 'show'])->name('show');
    Route::get('{employeeDocument}/download', [EmployeeDocumentController::class, 'download'])->name('download');
    Route::post('{employeeDocument}/approve', [EmployeeDocumentController::class, 'approve'])->name('approve');
    Route::post('{employeeDocument}/reject', [EmployeeDocumentController::class, 'reject'])->name('reject');
    Route::delete('{employeeDocument}', [EmployeeDocumentController::class, 'destroy'])->name('destroy');
});
