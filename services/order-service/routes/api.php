<?php
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;
Route::get('/health', [OrderController::class, 'health']);
Route::get('/orders', [OrderController::class, 'index']);
Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders/{id}', [OrderController::class, 'show']);
Route::put('/orders/{id}/status', [OrderController::class, 'updateStatus']);
