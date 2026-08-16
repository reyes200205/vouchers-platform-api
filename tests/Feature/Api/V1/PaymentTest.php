<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\VoucherStatus;
use App\Models\Branch;
use App\Models\CustomerPayment;
use App\Models\Distributor;
use App\Models\DistributorCategory;
use App\Models\Role;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function paymentSignInBusinessRole(User $user, string $roleCode, Branch $branch): void
{
    $role = Role::query()->firstOrCreate(['code' => $roleCode], ['name' => $roleCode]);
    $user->businessRoles()->attach($role, [
        'branch_id' => $branch->id,
        'assigned_at' => now(),
        'is_primary' => true,
    ]);
    Sanctum::actingAs($user);
}

function paymentActiveVoucher(Branch $branch, Distributor $distributor, float $balance = 22600.00): Voucher
{
    return Voucher::factory()->create([
        'branch_id' => $branch->id,
        'distributor_id' => $distributor->id,
        'status' => VoucherStatus::ACTIVO,
        'total_debt_amount' => 22600.00,
        'fortnightly_payment_amount' => 2825.00,
        'current_balance' => $balance,
        'payments_made' => 0,
    ]);
}

describe('Customer payments', function (): void {
    it('records a partial payment, updates the voucher balance and grants points', function (): void {
        $branch = Branch::factory()->create();
        $category = DistributorCategory::factory()->create(['points_per_1200' => 1]);
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'current_points' => 0,
        ]);
        $voucher = paymentActiveVoucher($branch, $distributor);

        $cashier = User::factory()->create();
        paymentSignInBusinessRole($cashier, 'cashier', $branch);

        $this->postJson('/api/v1/customer-payments', [
            'voucher_id' => $voucher->id,
            'amount' => '2825.00',
            'payment_method' => 'EFECTIVO',
        ])->assertCreated()
            ->assertJsonPath('data.amount', '2825.00')
            ->assertJsonPath('data.payment_method', 'EFECTIVO')
            ->assertJsonPath('data.is_partial', true);

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'current_balance' => 19775.00,
            'payments_made' => 1,
            'status' => 'PAGO_PARCIAL',
        ]);

        $payment = CustomerPayment::query()->firstOrFail();
        $this->assertDatabaseHas('point_movements', [
            'distributor_id' => $distributor->id,
            'voucher_id' => $voucher->id,
            'customer_payment_id' => $payment->id,
            'transaction_type' => 'GANADO_PUNTUAL',
            'points' => 2,
        ]);

        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'current_points' => 2,
        ]);
    });

    it('marks the voucher as PAGADO when the payment settles the balance', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $voucher = paymentActiveVoucher($branch, $distributor, 2825.00);

        $cashier = User::factory()->create();
        paymentSignInBusinessRole($cashier, 'cashier', $branch);

        $this->postJson('/api/v1/customer-payments', [
            'voucher_id' => $voucher->id,
            'amount' => '2825.00',
        ])->assertCreated()
            ->assertJsonPath('data.is_partial', false);

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'current_balance' => 0.00,
            'status' => 'PAGADO',
        ]);
    });

    it('rejects an overpayment', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $voucher = paymentActiveVoucher($branch, $distributor, 2825.00);

        $cashier = User::factory()->create();
        paymentSignInBusinessRole($cashier, 'cashier', $branch);

        $this->postJson('/api/v1/customer-payments', [
            'voucher_id' => $voucher->id,
            'amount' => '3000.00',
        ])->assertStatus(422)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'excede'));
    });

    it('rejects a payment on an already paid voucher', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $voucher = paymentActiveVoucher($branch, $distributor, 0.00);
        $voucher->update(['status' => VoucherStatus::PAGADO]);

        $cashier = User::factory()->create();
        paymentSignInBusinessRole($cashier, 'cashier', $branch);

        $this->postJson('/api/v1/customer-payments', [
            'voucher_id' => $voucher->id,
            'amount' => '500.00',
        ])->assertStatus(422);
    });

    it('reverses a payment and restores the voucher balance and points', function (): void {
        $branch = Branch::factory()->create();
        $category = DistributorCategory::factory()->create(['points_per_1200' => 1]);
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'current_points' => 2,
        ]);
        $voucher = paymentActiveVoucher($branch, $distributor);

        $payment = CustomerPayment::query()->create([
            'voucher_id' => $voucher->id,
            'customer_id' => $voucher->customer_id,
            'distributor_id' => $voucher->distributor_id,
            'payment_date' => now(),
            'amount' => 2825.00,
            'payment_method' => PaymentMethod::EFECTIVO,
            'is_partial' => true,
        ]);

        \App\Models\PointMovement::query()->create([
            'distributor_id' => $distributor->id,
            'voucher_id' => $voucher->id,
            'customer_payment_id' => $payment->id,
            'transaction_type' => \App\Enums\PointMovementType::GANADO_PUNTUAL,
            'points' => 2,
            'point_value_snapshot' => 2.00,
            'reason' => 'Pago de cliente registrado.',
        ]);

        $voucher->update([
            'payments_made' => 1,
            'current_balance' => 19775.00,
            'status' => VoucherStatus::PAGO_PARCIAL,
        ]);

        $cashier = User::factory()->create();
        paymentSignInBusinessRole($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/customer-payments/{$payment->id}/reverse", [
            'reason' => 'Pago duplicado',
        ])->assertOk()
            ->assertJsonPath('data.reversed_at', fn ($value) => $value !== null);

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'current_balance' => 22600.00,
            'payments_made' => 0,
        ]);

        $this->assertDatabaseHas('point_movements', [
            'distributor_id' => $distributor->id,
            'customer_payment_id' => $payment->id,
            'transaction_type' => 'REVERSO',
            'points' => -2,
        ]);

        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'current_points' => 0,
        ]);
    });

    it('forbids a distributor from registering payments', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $voucher = paymentActiveVoucher($branch, $distributor);

        $role = Role::query()->firstOrCreate(['code' => 'distributor'], ['name' => 'distributor']);
        $distributorUser = User::factory()->create(['person_id' => $distributor->person_id]);
        $distributorUser->businessRoles()->attach($role, [
            'branch_id' => $branch->id,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);
        Sanctum::actingAs($distributorUser);

        $this->postJson('/api/v1/customer-payments', [
            'voucher_id' => $voucher->id,
            'amount' => '100.00',
        ])->assertForbidden();
    });

    it('lists the payments of a voucher', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $voucher = paymentActiveVoucher($branch, $distributor);

        CustomerPayment::query()->create([
            'voucher_id' => $voucher->id,
            'customer_id' => $voucher->customer_id,
            'distributor_id' => $voucher->distributor_id,
            'payment_date' => now(),
            'amount' => 1000.00,
            'payment_method' => PaymentMethod::EFECTIVO,
        ]);

        $coordinator = User::factory()->create();
        paymentSignInBusinessRole($coordinator, 'coordinator', $branch);

        $this->getJson("/api/v1/vouchers/{$voucher->id}/payments")
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.amount', '1000.00');
    });
});