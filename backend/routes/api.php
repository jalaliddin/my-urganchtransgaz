<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    require base_path('routes/api/auth.php');

    Route::middleware('auth:sanctum')->group(function () {
        require base_path('routes/api/organizations.php');
        require base_path('routes/api/departments.php');
        require base_path('routes/api/employees.php');
    });
});
