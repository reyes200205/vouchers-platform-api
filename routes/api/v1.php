<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BranchManager\CustomerChangeRequestController;
use App\Http\Controllers\Cashier\CustomerController as CashierCustomerController;
use App\Http\Controllers\Checker\VerificadorController;
use App\Http\Controllers\BranchManager\BranchSettingController;
use App\Http\Controllers\Coordinator\CoordinadorController;
use App\Http\Controllers\Coordinator\CustomerController;
use App\Http\Controllers\Coordinator\CustomerTransferController;
use App\Http\Controllers\Distributor\CustomerController as DistributorCustomerController;
use App\Http\Controllers\Distributor\CustomerTransferController as DistributorCustomerTransferController;
use App\Http\Controllers\Employees\EmployeesController;
use App\Http\Controllers\GeneralManager\ApplicationDecisionController;
use App\Http\Controllers\GeneralManager\BranchController;
use App\Http\Controllers\GeneralManager\DistributorCategoryController;
use App\Http\Controllers\GeneralManager\FinancialProductController;
use App\Http\Controllers\GeneralManager\PointSettingController;
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
    Route::middleware('business.ability:branches.view')->group(function (): void {
        Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
        Route::get('/branches/{branch}', [BranchController::class, 'show'])->middleware('business.ability:branches.view,branch')->name('branches.show');
    });

    Route::middleware('business.ability:branches.manage')->group(function (): void {
        Route::post('/branches', [BranchController::class, 'store'])->name('branches.store');
        Route::patch('/branches/{branch}', [BranchController::class, 'update'])->middleware('business.ability:branches.manage,branch')->name('branches.update');
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

    Route::middleware('business.ability:categories.view')->group(function (): void {
        Route::get('/distributor-categories', [DistributorCategoryController::class, 'index'])->name('distributor-categories.index');
        Route::get('/distributor-categories/{distributorCategory}', [DistributorCategoryController::class, 'show'])->name('distributor-categories.show');
    });

    Route::middleware('business.ability:categories.manage')->group(function (): void {
        Route::post('/distributor-categories', [DistributorCategoryController::class, 'store'])->name('distributor-categories.store');
        Route::patch('/distributor-categories/{distributorCategory}', [DistributorCategoryController::class, 'update'])->name('distributor-categories.update');
    });

    Route::middleware('business.ability:point-settings.view')->get('/point-settings', [PointSettingController::class, 'show'])->name('point-settings.show');
    Route::middleware('business.ability:point-settings.manage')->patch('/point-settings', [PointSettingController::class, 'update'])->name('point-settings.update');

    Route::middleware('business.ability:applications.view')->get('/applications', [CoordinadorController::class, 'index'])->name('applications.index');
    Route::post('/applications', [CoordinadorController::class, 'store'])->name('applications.store');
    Route::middleware('business.ability:applications.assign-verifier,application')->patch('/applications/{application}/verifier', [CoordinadorController::class, 'assignVerifier'])->name('applications.assign-verifier');
    Route::middleware('business.ability:applications.verify,application')->post('/applications/{application}/verification', [VerificadorController::class, 'verify'])->name('applications.verify');
    Route::middleware('business.ability:applications.decide,application')->post('/applications/{application}/decision', [ApplicationDecisionController::class, 'decide'])->name('applications.decide');

    Route::middleware('business.ability:customers.view')->group(function (): void {
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->middleware('business.ability:customers.view,customer')->name('customers.show');
    });

    Route::middleware('business.ability:customers.create')->post('/customers', [DistributorCustomerController::class, 'store'])->name('customers.store');
    Route::middleware('business.ability:customers.verify,customer')->patch('/customers/{customer}/verify', [CashierCustomerController::class, 'verify'])->name('customers.verify');
    Route::middleware('business.ability:customers.update.request,customer')->post('/customers/{customer}/change-requests', [CashierCustomerController::class, 'storeChangeRequest'])->name('customers.change-requests.store');

    Route::middleware('business.ability:customers.update.approve')->get('/customer-change-requests', [CustomerChangeRequestController::class, 'index'])->name('customer-change-requests.index');
    Route::middleware('business.ability:customers.update.approve,customerChangeRequest')->post('/customer-change-requests/{customerChangeRequest}/decision', [CustomerChangeRequestController::class, 'decide'])->name('customer-change-requests.decide');

    Route::middleware('business.ability:customers.transfer.view')->group(function (): void {
        Route::get('/customer-transfer-requests', [CustomerTransferController::class, 'index'])->name('customer-transfer-requests.index');
        Route::get('/distributor/customer-transfer-requests', [DistributorCustomerTransferController::class, 'index'])->name('distributor.customer-transfer-requests.index');
    });

    Route::middleware('business.ability:customers.transfer.request,customer')->post('/customers/{customer}/transfer-requests', [DistributorCustomerTransferController::class, 'store'])->name('customers.transfer-requests.store');
    Route::middleware('business.ability:customers.transfer.cancel,customerTransferRequest')->post('/customer-transfer-requests/{customerTransferRequest}/cancel', [DistributorCustomerTransferController::class, 'cancel'])->name('customer-transfer-requests.cancel');
    Route::middleware('business.ability:customers.transfer.decide,customerTransferRequest')->post('/customer-transfer-requests/{customerTransferRequest}/decision', [CustomerTransferController::class, 'decide'])->name('customer-transfer-requests.decide');
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
