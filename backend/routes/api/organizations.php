<?php

use App\Http\Controllers\Api\V1\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::apiResource('organizations', OrganizationController::class);
