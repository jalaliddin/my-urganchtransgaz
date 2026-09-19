<?php

use App\Http\Controllers\Api\V1\IssueCategoryController;
use Illuminate\Support\Facades\Route;

Route::apiResource('issue-categories', IssueCategoryController::class)->parameters(['issue-categories' => 'issue_category']);
