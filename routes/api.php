<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BarTransactionController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\OwnerReportController;
use App\Http\Controllers\Api\V1\StaffController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::delete('/auth/logout', [AuthController::class, 'logout']);

        Route::middleware('api.role:Owner,Waiter,Barista')->group(function (): void {
            Route::get('/auth/me', [AuthController::class, 'me']);
            Route::get('/menu-categories', [CategoryController::class, 'index']);
            Route::get('/menu-items', [MenuController::class, 'index']);
            Route::get('/orders', [OrderController::class, 'index']);
            Route::get('/orders/{orderId}', [OrderController::class, 'show'])->whereNumber('orderId');
            Route::get('/notifications/completed-orders', [NotificationController::class, 'completedOrders']);
        });

        Route::middleware('api.role:Owner,Barista')->group(function (): void {
            Route::post('/menu-categories', [CategoryController::class, 'store']);
            Route::patch('/menu-categories/{categoryId}', [CategoryController::class, 'update'])->whereNumber('categoryId');
            Route::delete('/menu-categories/{categoryId}', [CategoryController::class, 'destroy'])->whereNumber('categoryId');
            Route::post('/menu-items', [MenuController::class, 'store']);
            Route::patch('/menu-items/{menuItemId}', [MenuController::class, 'update'])->whereNumber('menuItemId');
            Route::patch('/menu-items/{menuItemId}/availability', [MenuController::class, 'availability'])->whereNumber('menuItemId');
            Route::delete('/menu-items/{menuItemId}', [MenuController::class, 'destroy'])->whereNumber('menuItemId');
        });

        Route::middleware('api.role:Waiter')->group(function (): void {
            Route::post('/orders', [OrderController::class, 'store']);
            Route::post('/orders/{orderId}/payments', [OrderController::class, 'pay'])->whereNumber('orderId');
            Route::post('/orders/{orderId}/cancellation-requests', [OrderController::class, 'requestCancellation'])->whereNumber('orderId');
            Route::post('/orders/{orderId}/void', [OrderController::class, 'void'])->whereNumber('orderId')->middleware('throttle:5,1');
        });

        Route::middleware('api.role:Owner')->prefix('owner')->group(function (): void {
            Route::get('/dashboard', [OwnerReportController::class, 'dashboard']);
            Route::get('/reports/financial', [OwnerReportController::class, 'financial']);
            Route::get('/cancellation-requests', [OrderController::class, 'cancellationRequests']);
            Route::patch('/orders/{orderId}/cancellation', [OrderController::class, 'decideCancellation'])->whereNumber('orderId');
            Route::apiResource('staff', StaffController::class)->parameters(['staff' => 'staffId']);
            Route::patch('/staff/{staffId}/status', [StaffController::class, 'status'])->whereNumber('staffId');
        });

        Route::middleware('api.role:Barista')->prefix('barista')->group(function (): void {
            Route::get('/operational', [BarTransactionController::class, 'operational']);
            Route::get('/transactions', [BarTransactionController::class, 'index']);
            Route::post('/transactions', [BarTransactionController::class, 'store']);
        });
    });
});
