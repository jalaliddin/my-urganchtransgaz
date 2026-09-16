<?php

use App\Http\Controllers\Api\V1\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('search', [SearchController::class, 'index'])->name('search.index');
