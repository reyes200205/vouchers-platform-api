<?php

declare(strict_types=1);

use App\Enums\CustomerDistributorRelationshipStatus;
use App\Enums\CustomerStatus;
use App\Enums\VoucherStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerDistributor;
use App\Models\Distributor;
use App\Models\DistributorCategory;
use App\Models\FinancialProduct;
use App\Models\Role;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function signInBusinessRole(User $user, string $roleCode, Branch $branch): void
{
    $role = Role::query()->firstOrCreate(['code' => $roleCode], ['name' => $roleCode]);
    $user->businessRoles()->attach($role, [
        'branch_id' => $branch->id,
        'assigned_at' => now(),
        'is_primary' => true,
    ]);
    Sanctum::actingAs($user);
}

function signInDistributor(User $user, Distributor $distributor): void
{
    $user->update(['person_id' => $distributor->person_id]);
    $role = Role::query()->firstOrCreate(['code' => 'distributor'], ['name' => 'distributor']);
    $user->businessRoles()->attach($role, [
        'branch_id' => $distributor->branch_id,
        'assigned_at' => now(),
        'is_primary' => true,
    ]);
    Sanctum::actingAs($user);
}

function voucherScenario(): array
{
    $branch = Branch::factory()->create();
    $category = DistributorCategory::factory()->create(['commission_percentage' => 8.0000]);
    $product = FinancialProduct::factory()->create();
    $distributor = Distributor::factory()->create([
        'branch_id' => $branch->id,
        'category_id' => $category->id,
        'credit_limit' => 30000,
        'available_credit' => 30000,
    ]);
    $customer = Customer::factory()->create([
        'branch_id' => $branch->id,
        'status' => CustomerStatus::ACTIVO,
        'verified_at' => now(),
    ]);
    CustomerDistributor::query()->create([
        'distributor_id' => $distributor->id,
        'customer_id' => $customer->id,
        'relationship_status' => CustomerDistributorRelationshipStatus::ACTIVA,
        'prevale_approved' => false,
        'blocked_due_to_relationship' => false,
        'linked_at' => now(),
    ]);

    return compact('branch', 'category', 'product', 'distributor', 'customer');
}

describe('Voucher request (pre-issue por la distribuidora)', function (): void {
    it('creates a pre-vale request when the distributor has 100% of credit available', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $user = User::factory()->create();
        signInDistributor($user, $distributor);

        $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated()
            ->assertJsonPath('data.is_pre_vale', true)
            ->assertJsonPath('data.status', 'PENDIENTE')
            ->assertJsonPath('data.requested_amount', '15000.00')
            ->assertJsonPath('data.snapshot.total_debt_amount', 22600)
            ->assertJsonPath('data.snapshot.fortnightly_payment_amount', 2825);

        $this->assertDatabaseHas('voucher_requests', [
            'distributor_id' => $distributor->id,
            'customer_id' => $customer->id,
            'is_pre_vale' => true,
            'status' => 'PENDIENTE',
        ]);
    });

    it('denies a first voucher beyond 50% plus tolerance', function (): void {
        ['branch' => $branch, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $product = FinancialProduct::factory()->create(['principal_amount' => 17000.00]);
        $user = User::factory()->create();
        signInDistributor($user, $distributor);

        $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'El monto supera el máximo permitido para el primer vale (50% del crédito disponible).');

        $this->assertDatabaseCount('voucher_requests', 0);
    });

    it('does not apply the 50% rule once the distributor is not at 100% available', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $distributor->update(['available_credit' => 25000]);
        $user = User::factory()->create();
        signInDistributor($user, $distributor);

        $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated()
            ->assertJsonPath('data.is_pre_vale', false);
    });

    it('rejects a request for a customer that is not verified', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor] = voucherScenario();
        $customer = Customer::factory()->create([
            'branch_id' => $branch->id,
            'status' => CustomerStatus::EN_VERIFICACION,
        ]);
        CustomerDistributor::query()->create([
            'distributor_id' => $distributor->id,
            'customer_id' => $customer->id,
            'relationship_status' => CustomerDistributorRelationshipStatus::ACTIVA,
        ]);
        $user = User::factory()->create();
        signInDistributor($user, $distributor);

        $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertStatus(422);

        $this->assertDatabaseCount('voucher_requests', 0);
    });

    it('rejects a request for a customer with an active voucher with balance', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        Voucher::factory()->create([
            'distributor_id' => $distributor->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'status' => VoucherStatus::ACTIVO,
            'current_balance' => 5000.00,
        ]);
        $user = User::factory()->create();
        signInDistributor($user, $distributor);

        $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertStatus(422);

        $this->assertDatabaseCount('voucher_requests', 0);
    });

    it('rejects a request for a customer not linked to the distributor', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor] = voucherScenario();
        $otherCustomer = Customer::factory()->create(['branch_id' => $branch->id]);
        $user = User::factory()->create();
        signInDistributor($user, $distributor);

        $this->postJson('/api/v1/vouchers', [
            'customer_id' => $otherCustomer->id,
            'financial_product_id' => $product->id,
        ])->assertStatus(422);

        $this->assertDatabaseCount('voucher_requests', 0);
    });

    it('rejects a request when available credit is not enough for the total debt', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $distributor->update(['credit_limit' => 30000, 'available_credit' => 20000]);
        $user = User::factory()->create();
        signInDistributor($user, $distributor);

        $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'El crédito disponible de la distribuidora es insuficiente para cubrir la deuda total del vale.');

        $this->assertDatabaseCount('voucher_requests', 0);
    });
});

describe('Voucher approval (coordinador/gerente)', function (): void {
    it('approves the request, creates the voucher and decrements available credit', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $distributorUser = User::factory()->create();
        signInDistributor($distributorUser, $distributor);

        $request = $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated()->json('data');

        $coordinator = User::factory()->create();
        signInBusinessRole($coordinator, 'coordinator', $branch);

        $this->postJson("/api/v1/voucher-requests/{$request['id']}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'APROBADO')
            ->assertJsonPath('data.is_pre_vale', true)
            ->assertJsonPath('data.voucher_number', 'V-1')
            ->assertJsonPath('data.total_debt_amount', '22600.00')
            ->assertJsonPath('data.fortnightly_payment_amount', '2825.00')
            ->assertJsonPath('data.approved_by_user_id', $coordinator->id);

        $this->assertDatabaseHas('vouchers', [
            'voucher_number' => 'V-1',
            'distributor_id' => $distributor->id,
            'customer_id' => $customer->id,
            'status' => 'APROBADO',
            'is_pre_vale' => true,
            'current_balance' => 22600.00,
        ]);

        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'available_credit' => 7400.00,
        ]);

        $this->assertDatabaseHas('voucher_requests', [
            'id' => $request['id'],
            'status' => 'APROBADO',
            'decided_by_user_id' => $coordinator->id,
        ]);

        $this->assertDatabaseHas('customer_distributor', [
            'customer_id' => $customer->id,
            'distributor_id' => $distributor->id,
            'prevale_approved' => true,
        ]);
    });

    it('rejects approval when the distributor no longer has enough credit', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $distributorUser = User::factory()->create();
        signInDistributor($distributorUser, $distributor);

        $request = $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated()->json('data');

        $distributor->decrement('available_credit', 24000);

        $coordinator = User::factory()->create();
        signInBusinessRole($coordinator, 'coordinator', $branch);

        $this->postJson("/api/v1/voucher-requests/{$request['id']}/approve")
            ->assertStatus(422)
            ->assertJsonPath('message', 'El crédito disponible de la distribuidora es insuficiente para aprobar el vale.');

        $this->assertDatabaseCount('vouchers', 0);
    });

    it('forbids the distributor from approving requests', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $distributorUser = User::factory()->create();
        signInDistributor($distributorUser, $distributor);

        $request = $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated()->json('data');

        $this->postJson("/api/v1/voucher-requests/{$request['id']}/approve")->assertForbidden();
    });
});

describe('Voucher disbursement (cajera)', function (): void {
    it('disburses an approved voucher capturing the transfer reference', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $voucher = Voucher::factory()->create([
            'distributor_id' => $distributor->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'financial_product_id' => $product->id,
            'status' => VoucherStatus::APROBADO,
        ]);

        $cashier = User::factory()->create();
        signInBusinessRole($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/vouchers/{$voucher->id}/disburse", [
            'transfer_reference' => 'SPEI-20260816-001',
            'authorized_number' => 'AUT-0001',
        ])->assertOk()
            ->assertJsonPath('data.status', 'ACTIVO')
            ->assertJsonPath('data.transfer_reference', 'SPEI-20260816-001')
            ->assertJsonPath('data.authorized_number', 'AUT-0001')
            ->assertJsonPath('data.disbursed_by_user_id', $cashier->id);

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'status' => 'ACTIVO',
            'transfer_reference' => 'SPEI-20260816-001',
            'authorized_number' => 'AUT-0001',
        ]);
    });

    it('rejects disbursement of a voucher that is not approved', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $voucher = Voucher::factory()->create([
            'distributor_id' => $distributor->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'financial_product_id' => $product->id,
            'status' => VoucherStatus::ACTIVO,
        ]);

        $cashier = User::factory()->create();
        signInBusinessRole($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/vouchers/{$voucher->id}/disburse", [
            'transfer_reference' => 'SPEI-20260816-002',
            'authorized_number' => 'AUT-0002',
        ])->assertStatus(422);
    });

    it('forbids a coordinator from disbursing vouchers', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $voucher = Voucher::factory()->create([
            'distributor_id' => $distributor->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'financial_product_id' => $product->id,
            'status' => VoucherStatus::APROBADO,
        ]);

        $coordinator = User::factory()->create();
        signInBusinessRole($coordinator, 'coordinator', $branch);

        $this->postJson("/api/v1/vouchers/{$voucher->id}/disburse", [
            'transfer_reference' => 'SPEI-20260816-003',
            'authorized_number' => 'AUT-0003',
        ])->assertForbidden();
    });
});

describe('Voucher views', function (): void {
    it('lets a distributor see only its own vouchers', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        Voucher::factory()->create([
            'distributor_id' => $distributor->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'financial_product_id' => $product->id,
            'status' => VoucherStatus::APROBADO,
        ]);

        $otherDistributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        Voucher::factory()->create([
            'distributor_id' => $otherDistributor->id,
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'financial_product_id' => $product->id,
            'status' => VoucherStatus::APROBADO,
        ]);

        $user = User::factory()->create();
        signInDistributor($user, $distributor);

        $this->getJson('/api/v1/distributor/vouchers')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.distributor_id', $distributor->id);
    });
});