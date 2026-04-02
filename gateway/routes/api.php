<?php

use App\Http\Controllers\GatewayController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [GatewayController::class, 'health']);

Route::prefix('users')->group(function () {
    Route::get('/', [GatewayController::class, 'listUsers']);
    Route::post('/', [GatewayController::class, 'createUser']);
    Route::get('/{id}', [GatewayController::class, 'getUser']);
});

Route::prefix('orders')->group(function () {
    Route::get('/', [GatewayController::class, 'listOrders']);
    Route::post('/', [GatewayController::class, 'createOrder']);
    Route::get('/{id}', [GatewayController::class, 'getOrder']);
    Route::put('/{id}/status', [GatewayController::class, 'updateOrderStatus']);
});

Route::prefix('products')->group(function () {
    Route::get('/', [GatewayController::class, 'listProducts']);
    Route::get('/{id}', [GatewayController::class, 'getProduct']);
});
