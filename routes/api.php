<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductStatusController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AttributeController;

/*
|--------------------------------------------------------------------------
| API Routes - Aplikasi Pemesanan Kriya Ukir UMKM Adi Ukiran
|--------------------------------------------------------------------------
*/

// ---- Publik (tanpa login) ----
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);

Route::get('/attributes', [AttributeController::class, 'index']);

// ---- Perlu login (pelanggan & owner) ----
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::get('/orders/{order}/status', [ProductStatusController::class, 'index']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    Route::apiResource('users', UserController::class);

    // ---- Khusus pelanggan ----
    Route::middleware('role:pelanggan')->group(function () {
        Route::post('/orders', [OrderController::class, 'store']);
    });

    // ---- Khusus owner ----
    Route::middleware('role:owner')->group(function () {
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);

        // Route::get('/attributes', [AttributeController::class, 'index']);
        Route::post('/attributes', [AttributeController::class, 'store']);
        Route::delete('/attributes/{attribute}', [AttributeController::class, 'destroy']);

        Route::put('/orders/{order}', [OrderController::class, 'update']);
        Route::post('/orders/{order}/status', [ProductStatusController::class, 'store']);

        Route::get('/reports/summary', [ReportController::class, 'summary']);

        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);


    });
});

Route::get('/login', function () {
    return response()->json([
        'message' => 'Unauthenticated. Silakan login lewat endpoint POST /api/login.',
    ], 401);
})->name('login');
