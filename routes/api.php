<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProfileController;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/profile', [ProfileController::class, 'show']);

    // Order routes
    Route::apiResource('/orders', OrderController::class)->only(['index', 'store']);
    Route::get('/orders/my', [OrderController::class, 'myOrders'])->name('orders.my');
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
});

// Broadcasting auth route
Route::middleware('auth:sanctum')->post('/broadcasting/auth', function (Request $request) {
    return Broadcast::auth($request);
});
