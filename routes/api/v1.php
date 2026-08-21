<?php

declare(strict_types=1);

use App\Http\Controllers\Administrator\GeneralManagerController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BranchManager\BranchSettingController;
use App\Http\Controllers\BranchManager\CategoryController;
use App\Http\Controllers\BranchManager\CustomerChangeRequestController;
use App\Http\Controllers\BranchManager\CutoffController as BranchManagerCutoffController;
use App\Http\Controllers\BranchManager\ProductController;
use App\Http\Controllers\Cashier\CustomerController as CashierCustomerController;
use App\Http\Controllers\Cashier\PaymentController as CashierPaymentController;
use App\Http\Controllers\Cashier\ReconciliationController as CashierReconciliationController;
use App\Http\Controllers\Cashier\VoucherController as CashierVoucherController;
use App\Http\Controllers\Checker\VerificadorController;
use App\Http\Controllers\Checker\VerificationPhotoController;
use App\Http\Controllers\Coordinator\CoordinadorController;
use App\Http\Controllers\Coordinator\CreditIncreaseController as CoordinatorCreditIncreaseController;
use App\Http\Controllers\Coordinator\CustomerController;
use App\Http\Controllers\Coordinator\CustomerTransferController;
use App\Http\Controllers\Coordinator\DistributorController as CoordinatorDistributorController;
use App\Http\Controllers\Coordinator\PaymentController as CoordinatorPaymentController;
use App\Http\Controllers\Coordinator\VoucherController as CoordinatorVoucherController;
use App\Http\Controllers\Distributor\CustomerController as DistributorCustomerController;
use App\Http\Controllers\Distributor\CustomerTransferController as DistributorCustomerTransferController;
use App\Http\Controllers\Distributor\PointController as DistributorPointController;
use App\Http\Controllers\Distributor\VoucherController as DistributorVoucherController;
use App\Http\Controllers\Employees\EmployeesController;
use App\Http\Controllers\GeneralManager\ApplicationDecisionController;
use App\Http\Controllers\GeneralManager\BranchController;
use App\Http\Controllers\GeneralManager\CreditIncreaseController as GeneralManagerCreditIncreaseController;
use App\Http\Controllers\GeneralManager\CutoffController as GeneralManagerCutoffController;
use App\Http\Controllers\GeneralManager\DashboardController;
use App\Http\Controllers\GeneralManager\DistributorCategoryController;
use App\Http\Controllers\GeneralManager\FinancialProductController;
use App\Http\Controllers\GeneralManager\InboxController;
use App\Http\Controllers\GeneralManager\PointController as GeneralManagerPointController;
use App\Http\Controllers\GeneralManager\PointSettingController;
use App\Http\Controllers\GeneralManager\ReconciliationController as GeneralManagerReconciliationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Staff\StaffController;
use App\Http\Controllers\System\AuditLogController;
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
    Route::middleware('business.ability:users.manage')->group(function (): void {
        Route::post('/general-managers', [GeneralManagerController::class, 'store'])->name('general-managers.store');
    });

    Route::middleware('business.ability:staff.view')->group(function (): void {
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
    });

    Route::middleware('business.ability:staff.manage')->group(function (): void {
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::patch('/staff/{user}', [StaffController::class, 'update'])->name('staff.update');
    });

    Route::middleware('business.ability:branches.manage')->group(function (): void {
        Route::get('/branches/available-managers', [BranchController::class, 'availableManagers'])->name('branches.available-managers');
    });

    Route::middleware('business.ability:inbox.view')->get('/general/inbox', [InboxController::class, 'index'])->name('general.inbox');

    Route::middleware('business.ability:platform.view')->get('/stats/dashboard', [DashboardController::class, 'index'])->name('stats.dashboard');

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

    Route::middleware('business.ability:products.view,branch')->get('/branches/{branch}/products', [ProductController::class, 'index'])->name('branch-products.index');
    Route::middleware('business.ability:products.manage,branch')->group(function (): void {
        Route::post('/branches/{branch}/products', [ProductController::class, 'store'])->name('branch-products.store');
        Route::patch('/branches/{branch}/products/{financialProduct}', [ProductController::class, 'update'])->name('branch-products.update');
    });

    Route::middleware('business.ability:categories.view,branch')->get('/branches/{branch}/categories', [CategoryController::class, 'index'])->name('branch-categories.index');
    Route::middleware('business.ability:categories.manage,branch')->group(function (): void {
        Route::post('/branches/{branch}/categories', [CategoryController::class, 'store'])->name('branch-categories.store');
        Route::patch('/branches/{branch}/categories/{distributorCategory}', [CategoryController::class, 'update'])->name('branch-categories.update');
    });
    Route::middleware('business.ability:applications.assign-verifier,branch')->get('/branches/{branch}/verifiers', [BranchController::class, 'verifiers'])->name('branches.verifiers');

    Route::middleware('business.ability:products.view')->group(function (): void {
        Route::get('/financial-products', [FinancialProductController::class, 'index'])->name('financial-products.index');
        Route::get('/financial-products/{financialProduct}', [FinancialProductController::class, 'show'])->name('financial-products.show');
    });

    Route::middleware('business.ability:products.manage.global')->group(function (): void {
        Route::post('/financial-products', [FinancialProductController::class, 'store'])->name('financial-products.store');
        Route::patch('/financial-products/{financialProduct}', [FinancialProductController::class, 'update'])->name('financial-products.update');
    });

    Route::middleware('business.ability:categories.view')->group(function (): void {
        Route::get('/distributor-categories', [DistributorCategoryController::class, 'index'])->name('distributor-categories.index');
        Route::get('/distributor-categories/{distributorCategory}', [DistributorCategoryController::class, 'show'])->name('distributor-categories.show');
    });

    Route::middleware('business.ability:categories.manage.global')->group(function (): void {
        Route::post('/distributor-categories', [DistributorCategoryController::class, 'store'])->name('distributor-categories.store');
        Route::patch('/distributor-categories/{distributorCategory}', [DistributorCategoryController::class, 'update'])->name('distributor-categories.update');
    });

    Route::middleware('business.ability:point-settings.view')->get('/point-settings', [PointSettingController::class, 'show'])->name('point-settings.show');
    Route::middleware('business.ability:point-settings.manage')->patch('/point-settings', [PointSettingController::class, 'update'])->name('point-settings.update');

    Route::middleware('business.ability:applications.view')->get('/applications', [CoordinadorController::class, 'index'])->name('applications.index');
    Route::middleware('business.ability:applications.view,application')->get('/applications/{application}', [CoordinadorController::class, 'show'])->name('applications.show');
    Route::post('/applications', [CoordinadorController::class, 'store'])->name('applications.store');
    Route::middleware('business.ability:applications.assign-verifier,application')->patch('/applications/{application}/verifier', [CoordinadorController::class, 'assignVerifier'])->name('applications.assign-verifier');
    Route::middleware('business.ability:applications.verify,application')->post('/applications/{application}/verification', [VerificadorController::class, 'verify'])->name('applications.verify');
    Route::middleware('business.ability:applications.verify,application')->post('/applications/{application}/verification-photos', [VerificationPhotoController::class, 'store'])->name('applications.verification-photos.store');
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

    Route::middleware('business.ability:vouchers.view')->group(function (): void {
        Route::get('/vouchers', [CoordinatorVoucherController::class, 'index'])->name('vouchers.index');
        Route::get('/vouchers/{voucher}', [CoordinatorVoucherController::class, 'show'])->name('vouchers.show');
        Route::get('/distributor/vouchers', [DistributorVoucherController::class, 'index'])->name('distributor.vouchers.index');
        Route::get('/distributor/voucher-requests', [DistributorVoucherController::class, 'requests'])->name('distributor.voucher-requests.index');
    });

    Route::middleware('business.ability:vouchers.pre-issue')->post('/vouchers', [DistributorVoucherController::class, 'store'])->name('vouchers.pre-issue');
    Route::middleware('business.ability:vouchers.approve')->get('/voucher-requests', [CoordinatorVoucherController::class, 'pendingRequests'])->name('voucher-requests.index');
    Route::middleware('business.ability:vouchers.approve,voucherRequest')->post('/voucher-requests/{voucherRequest}/approve', [CoordinatorVoucherController::class, 'approve'])->name('vouchers.approve');
    Route::middleware('business.ability:vouchers.reject,voucherRequest')->post('/voucher-requests/{voucherRequest}/reject', [CoordinatorVoucherController::class, 'reject'])->name('vouchers.reject');
    Route::middleware('business.ability:vouchers.disburse,voucher')->post('/vouchers/{voucher}/disburse', [CashierVoucherController::class, 'disburse'])->name('vouchers.disburse');

    Route::middleware('business.ability:credit-increase.view')->get('/credit-increase-requests', [GeneralManagerCreditIncreaseController::class, 'index'])->name('credit-increase-requests.index');
    Route::middleware('business.ability:credit-increase.request')->post('/credit-increase-requests', [CoordinatorCreditIncreaseController::class, 'store'])->name('credit-increase-requests.store');
    Route::middleware('business.ability:credit-increase.pre-authorize,creditIncreaseRequest')->post('/credit-increase-requests/{creditIncreaseRequest}/pre-authorize', [CoordinatorCreditIncreaseController::class, 'preAuthorize'])->name('credit-increase-requests.pre-authorize');
    Route::middleware('business.ability:credit-increase.decide,creditIncreaseRequest')->post('/credit-increase-requests/{creditIncreaseRequest}/decision', [GeneralManagerCreditIncreaseController::class, 'decide'])->name('credit-increase-requests.decide');

    Route::middleware('business.ability:distributors.view')->get('/distributors', [CoordinatorDistributorController::class, 'index'])->name('distributors.index');

    Route::middleware('business.ability:payments.view')->get('/customer-payments', [CoordinatorPaymentController::class, 'index'])->name('customer-payments.index');
    Route::middleware('business.ability:payments.view,voucher')->get('/vouchers/{voucher}/payments', [CoordinatorPaymentController::class, 'voucherPayments'])->name('vouchers.payments.index');
    Route::middleware('business.ability:payments.create')->post('/customer-payments', [CashierPaymentController::class, 'store'])->name('customer-payments.store');
    Route::middleware('business.ability:payments.reverse,customerPayment')->post('/customer-payments/{customerPayment}/reverse', [CashierPaymentController::class, 'reverse'])->name('customer-payments.reverse');

    Route::middleware('business.ability:cutoffs.view')->get('/cutoffs', [BranchManagerCutoffController::class, 'index'])->name('cutoffs.index');
    Route::middleware('business.ability:cutoffs.view,cutoff')->get('/cutoffs/{cutoff}', [BranchManagerCutoffController::class, 'show'])->name('cutoffs.show');
    Route::middleware('business.ability:cutoffs.manage,branch')->post('/branches/{branch}/cutoffs/generate', [BranchManagerCutoffController::class, 'generate'])->name('cutoffs.generate');
    Route::middleware('business.ability:cutoffs.manage,cutoff')->post('/cutoffs/{cutoff}/reprocess', [GeneralManagerCutoffController::class, 'reprocess'])->name('cutoffs.reprocess');
    Route::middleware('business.ability:cutoffs.manage,cutoff')->post('/cutoffs/{cutoff}/close', [GeneralManagerCutoffController::class, 'close'])->name('cutoffs.close');

    Route::middleware('business.ability:reconciliations.import,branch')->post('/branches/{branch}/reconciliations/import', [CashierReconciliationController::class, 'import'])->name('reconciliations.import');
    Route::middleware('business.ability:reconciliations.view')->get('/reconciliations/bank-transactions', [CashierReconciliationController::class, 'bankTransactions'])->name('reconciliations.bank-transactions');
    Route::middleware('business.ability:reconciliations.view')->get('/reconciliations', [CashierReconciliationController::class, 'reconciliations'])->name('reconciliations.index');
    Route::middleware('business.ability:reconciliations.manual,bankTransaction')->post('/reconciliations/bank-transactions/{bankTransaction}/manual-match', [GeneralManagerReconciliationController::class, 'manualMatch'])->name('reconciliations.manual-match');
    Route::middleware('business.ability:reconciliations.verify,reconciliation')->post('/reconciliations/{reconciliation}/verify', [GeneralManagerReconciliationController::class, 'verify'])->name('reconciliations.verify');

    Route::middleware('business.ability:points.redeem.request,distributor')->post('/distributors/{distributor}/points/redeem', [DistributorPointController::class, 'redeem'])->name('points.redeem.request');
    Route::middleware('business.ability:points.view,distributor')->get('/distributors/{distributor}/points/redemptions', [DistributorPointController::class, 'myRedemptions'])->name('points.redemptions.mine');
    Route::middleware('business.ability:points.view')->get('/point-redemptions', [GeneralManagerPointController::class, 'index'])->name('point-redemptions.index');
    Route::middleware('business.ability:points.redeem.decide,pointRedemption')->post('/point-redemptions/{pointRedemption}/decision', [GeneralManagerPointController::class, 'decide'])->name('point-redemptions.decide');
    Route::middleware('business.ability:points.category,distributor')->patch('/distributors/{distributor}/category', [GeneralManagerPointController::class, 'updateCategory'])->name('distributors.category.update');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
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
    Route::middleware('business.ability:audit-logs.view')->get('system/audit-logs', [AuditLogController::class, 'index'])->name('system.audit-logs.index');
});

Route::prefix('auth')->group(function (): void {
    // Public routes (5/min - brute force protection)
    Route::middleware('throttle:auth')->group(function (): void {
        Route::post('login', [AuthController::class, 'login'])->name('api.v1.login');
        Route::post('mfa/verify', [AuthController::class, 'verifyMfa'])->name('api.v1.mfa.verify');
        Route::post('mfa/resend', [AuthController::class, 'resendMfa'])->name('api.v1.mfa.resend');
    });

    // Protected routes with authenticated rate limiter (120/min)
    Route::middleware(['auth:sanctum', 'throttle:authenticated'])->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout'])->name('api.v1.logout');
        Route::get('me', [AuthController::class, 'me'])->name('api.v1.me');
        Route::post('change-password', [AuthController::class, 'changePassword'])->name('api.v1.change-password');
    });
});
