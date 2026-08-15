<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Applications\ApplicationController;
use App\Http\Controllers\Branches\BranchSettingController;
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
    Route::middleware('business.ability:branches.view')->group(function (): void {
        Route::get('/branches', [BranchesController::class, 'index'])->name('branches.index');
        Route::get('/branches/{branch}', [BranchesController::class, 'show'])->middleware('business.ability:branches.view,branch')->name('branches.show');
    });

    Route::middleware('business.ability:branches.manage')->group(function (): void {
        Route::post('/branches', [BranchesController::class, 'store'])->name('branches.store');
        Route::patch('/branches/{branch}', [BranchesController::class, 'update'])->middleware('business.ability:branches.manage,branch')->name('branches.update');
    });

    Route::middleware('business.ability:branch-settings.view,branch')->get('/branches/{branch}/settings', [BranchSettingController::class, 'show'])->name('branch-settings.show');
    Route::middleware('business.ability:branch-settings.manage,branch')->patch('/branches/{branch}/settings', [BranchSettingController::class, 'update'])->name('branch-settings.update');

    Route::middleware('business.ability:products.view')->group(function (): void {
        Route::get('/financial-products', [FinancialProductController::class, 'index'])->name('financial-products.index');
        Route::get('/financial-products/{financialProduct}', [FinancialProductController::class, 'show'])->name('financial-products.show');
    });

    Route::middleware('business.ability:products.manage')->group(function (): void {
        Route::post('/financial-products', [FinancialProductController::class, 'store'])->name('financial-products.store');
        Route::patch('/financial-products/{financialProduct}', [FinancialProductController::class, 'update'])->name('financial-products.update');
    });

    Route::middleware('business.ability:applications.view')->get('/applications', [ApplicationController::class, 'index'])->name('applications.index');
    Route::post('/applications', [ApplicationController::class, 'store'])->name('applications.store');
    Route::middleware('business.ability:applications.assign-verifier,application')->patch('/applications/{application}/verifier', [ApplicationController::class, 'assignVerifier'])->name('applications.assign-verifier');
    Route::middleware('business.ability:applications.verify,application')->post('/applications/{application}/verification', [ApplicationController::class, 'verify'])->name('applications.verify');
    Route::middleware('business.ability:applications.decide,application')->post('/applications/{application}/decision', [ApplicationController::class, 'decide'])->name('applications.decide');
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
