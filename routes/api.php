<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\OrderController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
   
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/profile', function (Request $request) {
            return $request->user();
        });     

    Route::apiResource('/orders', OrderController::class)->only(['index', 'store']);
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
});
