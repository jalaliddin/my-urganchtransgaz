<?php

use App\Http\Controllers\Api\V1\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::apiResource('employees', EmployeeController::class);
Route::get('employees/{employee}/photo', [EmployeeController::class, 'photo'])->name('employees.photo');
