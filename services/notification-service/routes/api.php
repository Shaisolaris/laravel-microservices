<?php
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;
Route::get('/health', [NotificationController::class, 'health']);
