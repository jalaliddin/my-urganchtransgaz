<?php

use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\EmployeeImportController;
use Illuminate\Support\Facades\Route;

Route::get('employees/import/template', [EmployeeImportController::class, 'template'])->name('employees.import.template');
Route::post('employees/import', [EmployeeImportController::class, 'store'])->name('employees.import.store');

Route::apiResource('employees', EmployeeController::class);
Route::get('employees/{employee}/photo', [EmployeeController::class, 'photo'])->name('employees.photo');
Route::put('employees/{employee}/role', [EmployeeController::class, 'updateRole'])->name('employees.role.update');
