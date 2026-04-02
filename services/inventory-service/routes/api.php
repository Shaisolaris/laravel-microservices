<?php
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;
Route::get('/health', [ProductController::class, 'health']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::post('/products/{id}/stock', [ProductController::class, 'updateStock']);
