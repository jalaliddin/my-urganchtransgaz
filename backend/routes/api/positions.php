<?php

use App\Http\Controllers\Api\V1\PositionController;
use Illuminate\Support\Facades\Route;

Route::apiResource('positions', PositionController::class);
