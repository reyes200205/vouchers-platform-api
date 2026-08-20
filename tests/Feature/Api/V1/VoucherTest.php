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
    $role = Role::query()->firstOrCreate(['code' => 'distributor'], ['name' => 'distributor']);
    $user->update(['person_id' => $distributor->person_id]);
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

    it('does not apply the 50% rule once the distributor is not at 100% available and there is no pending reactivation', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $distributor->update(['credit_limit' => 30000, 'available_credit' => 25000]);
        $user = User::factory()->create();
        signInDistributor($user, $distributor);

        $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated()
            ->assertJsonPath('data.is_pre_vale', false);
    });

    it('reapplies the 50% rule after a credit increase even if the distributor is not at 100% available', function (): void {
        // Regla confirmada: si el gerente autoriza un aumento de línea mientras
        // la distribuidora ya tiene crédito usado, el siguiente vale vuelve a
        // respetar el 50% del DISPONIBLE + tolerancia, aunque el disponible no
        // sea igual al límite total (ver Distributor::prevale_required_after_credit_increase_at).
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $distributor->update([
            'credit_limit' => 30000,
            'available_credit' => 25000, // ya tiene 5,000 usados
            'prevale_required_after_credit_increase_at' => now(),
        ]);
        $user = User::factory()->create();
        signInDistributor($user, $distributor);

        $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'El monto supera el máximo permitido para el primer vale (50% del crédito disponible).');

        $this->assertDatabaseCount('voucher_requests', 0);
    });

    it('allows a request for a customer that is not verified yet (verification happens at disbursement)', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor] = voucherScenario();
        $customer = Customer::factory()->create([
            'branch_id' => $branch->id,
            'status' => CustomerStatus::EN_VERIFICACION,
            'verified_at' => null,
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
        ])->assertCreated();

        $this->assertDatabaseHas('voucher_requests', [
            'distributor_id' => $distributor->id,
            'customer_id' => $customer->id,
            'status' => 'PENDIENTE',
        ]);
    });

    it('rejects a request for a customer that is blocked, delinquent or inactive', function (string $status): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor] = voucherScenario();
        $customer = Customer::factory()->create([
            'branch_id' => $branch->id,
            'status' => $status,
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
        ])->assertStatus(422)
            ->assertJsonPath('message', 'El cliente no puede recibir un vale nuevo por su estado actual.');

        $this->assertDatabaseCount('voucher_requests', 0);
    })->with([
        'bloqueado' => CustomerStatus::BLOQUEADO->value,
        'moroso' => CustomerStatus::MOROSO->value,
        'inactivo' => CustomerStatus::INACTIVO->value,
    ]);

    it('rejects a request for a customer that already has a pending voucher request', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $user = User::factory()->create();
        signInDistributor($user, $distributor);

        $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated();

        $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'El cliente ya tiene una solicitud de vale pendiente de aprobación.');

        $this->assertDatabaseCount('voucher_requests', 1);
    });

    it('activates the customer when their pre-vale request is approved', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor] = voucherScenario();
        $customer = Customer::factory()->create([
            'branch_id' => $branch->id,
            'status' => CustomerStatus::EN_VERIFICACION,
            'verified_at' => null,
        ]);
        CustomerDistributor::query()->create([
            'distributor_id' => $distributor->id,
            'customer_id' => $customer->id,
            'relationship_status' => CustomerDistributorRelationshipStatus::ACTIVA,
        ]);
        $distributorUser = User::factory()->create();
        signInDistributor($distributorUser, $distributor);

        $requestId = $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated()->json('data.id');

        $cashier = User::factory()->create();
        signInBusinessRole($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/voucher-requests/{$requestId}/approve")
            ->assertOk();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'status' => 'ACTIVO',
        ]);
        $this->assertDatabaseMissing('customers', [
            'id' => $customer->id,
            'verified_at' => null,
        ]);
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

    it('rejects a request for a product tied to a different distributor category', function (): void {
        ['branch' => $branch, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $otherCategory = DistributorCategory::factory()->create();
        $product = FinancialProduct::factory()->create(['category_id' => $otherCategory->id]);
        $user = User::factory()->create();
        signInDistributor($user, $distributor);

        $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'El producto seleccionado no está disponible para la categoría de esta distribuidora.');

        $this->assertDatabaseCount('voucher_requests', 0);
    });

    it('allows a request for a product tied to the distributor own category', function (): void {
        ['branch' => $branch, 'category' => $category, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $product = FinancialProduct::factory()->create(['category_id' => $category->id]);
        $user = User::factory()->create();
        signInDistributor($user, $distributor);

        $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated();
    });

    it('allows a request for a product with no category regardless of the distributor category', function (): void {
        ['branch' => $branch, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $product = FinancialProduct::factory()->create(['category_id' => null]);
        $user = User::factory()->create();
        signInDistributor($user, $distributor);

        $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated();
    });

    it('rejects a request when available credit is not enough to cover the voucher principal', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        // Producto por default: principal 15,000 -- 10,000 disponibles no alcanzan.
        $distributor->update(['credit_limit' => 30000, 'available_credit' => 10000]);
        $user = User::factory()->create();
        signInDistributor($user, $distributor);

        $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'El crédito disponible de la distribuidora es insuficiente para cubrir el monto del vale.');

        $this->assertDatabaseCount('voucher_requests', 0);
    });
});

describe('Voucher approval (cajera/gerente)', function (): void {
    it('approves the request, creates the voucher and decrements available credit', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $distributorUser = User::factory()->create();
        signInDistributor($distributorUser, $distributor);

        $request = $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated()->json('data');

        $cashier = User::factory()->create();
        signInBusinessRole($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/voucher-requests/{$request['id']}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'APROBADO')
            ->assertJsonPath('data.is_pre_vale', true)
            ->assertJsonPath('data.voucher_number', 'V-1')
            ->assertJsonPath('data.total_debt_amount', '22600.00')
            ->assertJsonPath('data.fortnightly_payment_amount', '2825.00')
            ->assertJsonPath('data.approved_by_user_id', $cashier->id);

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
            'available_credit' => 15000.00,
        ]);

        $this->assertDatabaseHas('voucher_requests', [
            'id' => $request['id'],
            'status' => 'APROBADO',
            'decided_by_user_id' => $cashier->id,
        ]);

        $this->assertDatabaseHas('customer_distributor', [
            'customer_id' => $customer->id,
            'distributor_id' => $distributor->id,
            'prevale_approved' => true,
        ]);
    });

    it('clears the credit-increase reactivation flag once the next voucher is approved', function (): void {
        ['branch' => $branch, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $product = FinancialProduct::factory()->create(['principal_amount' => 10000.00]);
        $distributor->update([
            'credit_limit' => 30000,
            'available_credit' => 25000,
            'prevale_required_after_credit_increase_at' => now(),
        ]);
        $distributorUser = User::factory()->create();
        signInDistributor($distributorUser, $distributor);

        $request = $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated()->json('data');

        $cashier = User::factory()->create();
        signInBusinessRole($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/voucher-requests/{$request['id']}/approve")
            ->assertOk()
            ->assertJsonPath('data.is_pre_vale', true);

        expect($distributor->refresh()->prevale_required_after_credit_increase_at)->toBeNull();
        expect((float) $distributor->available_credit)->toBe(15000.00);
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

        $cashier = User::factory()->create();
        signInBusinessRole($cashier, 'cashier', $branch);

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

    it('forbids a coordinator from approving requests', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $distributorUser = User::factory()->create();
        signInDistributor($distributorUser, $distributor);

        $request = $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated()->json('data');

        $coordinator = User::factory()->create();
        signInBusinessRole($coordinator, 'coordinator', $branch);

        $this->postJson("/api/v1/voucher-requests/{$request['id']}/approve")->assertForbidden();
    });
});

describe('Voucher rejection (cajera/gerente)', function (): void {
    it('rejects a pending request without touching the credit (nothing was reserved yet)', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $distributorUser = User::factory()->create();
        signInDistributor($distributorUser, $distributor);

        $request = $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated()->json('data');

        $this->assertDatabaseHas('distributors', ['id' => $distributor->id, 'available_credit' => 30000.00]);

        $cashier = User::factory()->create();
        signInBusinessRole($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/voucher-requests/{$request['id']}/reject", [
            'reason' => 'No paso la verificacion de identidad.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'RECHAZADO')
            ->assertJsonPath('data.rejection_reason', 'No paso la verificacion de identidad.');

        $this->assertDatabaseHas('voucher_requests', [
            'id' => $request['id'],
            'status' => 'RECHAZADO',
            'decided_by_user_id' => $cashier->id,
        ]);
        $this->assertDatabaseCount('vouchers', 0);
       $this->assertDatabaseHas('distributors', ['id' => $distributor->id, 'available_credit' => 30000.00]);
    });

    it('requires a reason to reject', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $distributorUser = User::factory()->create();
        signInDistributor($distributorUser, $distributor);

        $request = $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated()->json('data');

        $cashier = User::factory()->create();
        signInBusinessRole($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/voucher-requests/{$request['id']}/reject", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
    });

    it('forbids the distributor from rejecting requests', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor, 'customer' => $customer] = voucherScenario();
        $distributorUser = User::factory()->create();
        signInDistributor($distributorUser, $distributor);

        $request = $this->postJson('/api/v1/vouchers', [
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
        ])->assertCreated()->json('data');

        $this->postJson("/api/v1/voucher-requests/{$request['id']}/reject", ['reason' => 'x'])
            ->assertForbidden();
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

    it('rolls the first payment_due_date to the NEXT cutoff period, never the current one', function (): void {
        // El dia 27 cae en el periodo 16-31 (branch_settings.cutoff_day = 15 por
        // default). El vale se acaba de pedir, asi que el primer pago NO debe
        // caer el 30/31 de ESTE mes (el periodo actual, a solo unos dias) --
        // debe caer hasta el 15 del mes SIGUIENTE. Ver CutoffPeriodCalculator.
        $this->travelTo(now()->setDate(2026, 8, 27)->setTime(10, 0, 0));

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
            'transfer_reference' => 'SPEI-20260827-777',
            'authorized_number' => 'AUT-0777',
        ])->assertOk()
            ->assertJsonPath('data.payment_due_date', '2026-09-15');

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'payment_due_date' => '2026-09-15',
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

    it('rejects disbursement when the customer has not been verified by the cashier', function (): void {
        ['branch' => $branch, 'product' => $product, 'distributor' => $distributor] = voucherScenario();
        $customer = Customer::factory()->create([
            'branch_id' => $branch->id,
            'status' => CustomerStatus::EN_VERIFICACION,
            'verified_at' => null,
        ]);
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
            'transfer_reference' => 'SPEI-20260816-004',
            'authorized_number' => 'AUT-0004',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'El cliente debe ser verificado por la cajera antes de poder recibir el vale.');

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'status' => 'APROBADO',
        ]);
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
