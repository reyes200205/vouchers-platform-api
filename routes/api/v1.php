<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Branches\BranchesController;
use App\Http\Controllers\Employees\EmployeesController;
use App\Http\Controllers\System\RolesController;
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
});


/*
|--------------------------------------------------------------------------
| Route for create employees
|
*/
Route::middleware(['auth:sanctum', 'throttle:authenticated'])->group(function (): void {
    Route::get('employees', [EmployeesController::class, 'index'])->name('employee.index');
    Route::post('employees', [EmployeesController::class, 'store'])->name('employee.store');

});


/*
|--------------------------------------------------------------------------
| Route for system roles
|
*/
Route::middleware(['auth:sanctum', 'throttle:authenticated'])->group(function (): void {
    Route::get('system/roles', [RolesController::class, 'index'])->name('system.roles.index');    
});















Route::prefix('auth')->group(function (): void {
    // Public routes wr (5/min - brute force protection)
    Route::middleware('throttle:auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->name('api.v1.register');
        Route::post('login', [AuthController::class, 'login'])->name('api.v1.login');
    });

    // Protected routes with authenticated rate limiter (120/min)
    Route::middleware(['auth:sanctum', 'throttle:authenticated'])->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout'])->name('api.v1.logout');
        Route::get('me', [AuthController::class, 'me'])->name('api.v1.me');

        // Email verification
        Route::post('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
            ->middleware('signed')
            ->name('verification.verify');
        Route::post('email/resend', [AuthController::class, 'resendVerificationEmail'])
            ->middleware('throttle:6,1')
            ->name('verification.send');
    });

    // Password reset routes (public with rate limiting)
    Route::middleware('throttle:6,1')->group(function (): void {
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])
            ->name('password.email');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])
            ->name('password.reset');
    });
});
