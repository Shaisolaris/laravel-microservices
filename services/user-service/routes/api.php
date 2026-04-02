<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [UserController::class, 'health']);
Route::apiResource('users', UserController::class)->only(['index', 'show', 'store', 'update']);
