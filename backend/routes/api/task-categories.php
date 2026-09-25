<?php

use App\Http\Controllers\Api\V1\TaskCategoryController;
use Illuminate\Support\Facades\Route;

Route::apiResource('task-categories', TaskCategoryController::class)->parameters(['task-categories' => 'task_category']);
