<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Branches\BranchesController;
use App\Http\Controllers\FinancialProducts\FinancialProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
|
| Routes for API version 1.
|
*/

/*
|--------------------------------------------------------------------------
|
| Routes for Test
*/

Route::get('ping', fn () => response()->json([
    'message' => 'pong',
]))->name('api.v1.ping');

/*
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Routes for
*/
Route::middleware(['auth:sanctum', 'throttle:authenticated'])->group(function (): void {
    Route::get('/branches', [BranchesController::class, 'index'])->name('branch.index');
    Route::get('/branches/{id}', [BranchesController::class, 'show'])->whereNumber('id')->name('branch.show');
    Route::post('/branches', [BranchesController::class, 'store'])->name('branch.store');

    Route::middleware('business.ability:products.view')->group(function (): void {
        Route::get('/financial-products', [FinancialProductController::class, 'index'])->name('financial-products.index');
        Route::get('/financial-products/{financialProduct}', [FinancialProductController::class, 'show'])->name('financial-products.show');
    });

    Route::middleware('business.ability:products.manage')->group(function (): void {
        Route::post('/financial-products', [FinancialProductController::class, 'store'])->name('financial-products.store');
        Route::patch('/financial-products/{financialProduct}', [FinancialProductController::class, 'update'])->name('financial-products.update');
    });
});

Route::prefix('auth')->group(function (): void {
    // Public routes (5/min - brute force protection)
    Route::middleware('throttle:auth')->group(function (): void {
        Route::post('login', [AuthController::class, 'login'])->name('api.v1.login');
    });

    // Protected routes with authenticated rate limiter (120/min)
    Route::middleware(['auth:sanctum', 'throttle:authenticated'])->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout'])->name('api.v1.logout');
        Route::get('me', [AuthController::class, 'me'])->name('api.v1.me');
    });
});
