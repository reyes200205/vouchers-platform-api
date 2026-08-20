<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerChangeRequest;
use App\Models\CustomerDistributor;
use App\Models\CustomerTransferRequest;
use App\Models\Distributor;
use App\Models\FinancialProduct;
use App\Models\Role;
use App\Models\User;
use App\Models\Voucher;
use App\Enums\CustomerDistributorRelationshipStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function signInWithBusinessRole(User $user, string $roleCode, Branch $branch): void
{
    $role = Role::query()->firstOrCreate(['code' => $roleCode], ['name' => $roleCode]);
    $user->businessRoles()->attach($role, [
        'branch_id' => $branch->id,
        'assigned_at' => now(),
        'is_primary' => true,
    ]);
    Sanctum::actingAs($user);
}

function signInAsDistributor(User $user, Distributor $distributor): void
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

function customerPayload(): array
{
    return [
        'person' => [
            'first_name' => 'Juan',
            'last_name' => 'Perez',
            'second_last_name' => 'Lopez',
            'curp' => 'JUAN900101HNLXYZ05',
            'mobile_phone' => '5551234567',
            'email' => 'juan@example.com',
            'street' => 'Calle Uno',
            'external_number' => '12',
        ],
    ];
}

describe('Customer lifecycle', function (): void {
    it('allows a distributor to create a customer pending verification', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $distributorUser = User::factory()->create();
        signInAsDistributor($distributorUser, $distributor);

        $response = $this->postJson('/api/v1/customers', customerPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'EN_VERIFICACION')
            ->assertJsonPath('data.verified_at', null);

        $customerId = $response->json('data.id');

        $this->assertDatabaseHas('customers', [
            'id' => $customerId,
            'branch_id' => $branch->id,
            'status' => 'EN_VERIFICACION',
        ]);
        $this->assertDatabaseHas('customer_distributor', [
            'customer_id' => $customerId,
            'distributor_id' => $distributor->id,
            'relationship_status' => 'ACTIVA',
        ]);
    });

    it('lets a distributor list its own customers in the paginated shape the frontend expects', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $customer = Customer::factory()->create(['branch_id' => $branch->id]);
        CustomerDistributor::query()->create([
            'distributor_id' => $distributor->id,
            'customer_id' => $customer->id,
            'relationship_status' => CustomerDistributorRelationshipStatus::ACTIVA,
        ]);
        $distributorUser = User::factory()->create();
        signInAsDistributor($distributorUser, $distributor);

        $this->getJson("/api/v1/customers?distributor_id={$distributor->id}")
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $customer->id)
            ->assertJsonPath('data.meta.total', 1);
    });

    it('forbids a coordinator from creating customers', function (): void {
        $branch = Branch::factory()->create();
        $coordinator = User::factory()->create();
        signInWithBusinessRole($coordinator, 'coordinator', $branch);

        $this->postJson('/api/v1/customers', customerPayload())->assertForbidden();

        $this->assertDatabaseCount('customers', 0);
    });

    it('allows a cashier to verify a customer', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $customer = Customer::factory()->create([
            'branch_id' => $branch->id,
            'status' => 'EN_VERIFICACION',
        ]);
        CustomerDistributor::query()->create([
            'distributor_id' => $distributor->id,
            'customer_id' => $customer->id,
            'relationship_status' => CustomerDistributorRelationshipStatus::ACTIVA,
        ]);

        $cashier = User::factory()->create();
        signInWithBusinessRole($cashier, 'cashier', $branch);

        $this->patchJson("/api/v1/customers/{$customer->id}/verify", [
            'id_front_photo' => 'photos/front.jpg',
            'proof_of_address_photo' => 'photos/address.jpg',
        ])->assertOk()
            ->assertJsonPath('data.status', 'ACTIVO')
            ->assertJsonPath('data.verified_by_user_id', $cashier->id);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'verified_by_user_id' => $cashier->id,
        ]);
        $this->assertNotNull($customer->fresh()->verified_at);
    });

    it('forbids a distributor from verifying customers', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $customer = Customer::factory()->create(['branch_id' => $branch->id]);

        $distributorUser = User::factory()->create();
        signInAsDistributor($distributorUser, $distributor);

        $this->patchJson("/api/v1/customers/{$customer->id}/verify", [])->assertForbidden();
    });
});

describe('Customer change requests', function (): void {
    it('lets a cashier request a contact change and a manager approve it', function (): void {
        $branch = Branch::factory()->create();
        $customer = Customer::factory()->active()->create(['branch_id' => $branch->id]);

        $cashier = User::factory()->create();
        signInWithBusinessRole($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/customers/{$customer->id}/change-requests", [
            'change_type' => 'CONTACT',
            'new_values' => [
                'mobile_phone' => '5559998877',
                'email' => 'nuevo@example.com',
            ],
            'evidence' => ['photos/ine.jpg'],
            'notes' => 'Cliente cambio de telefono',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'PENDIENTE')
            ->assertJsonPath('data.change_type', 'CONTACT');

        $this->assertDatabaseHas('customer_change_requests', [
            'customer_id' => $customer->id,
            'change_type' => 'CONTACT',
            'status' => 'PENDIENTE',
        ]);

        $requestId = CustomerChangeRequest::query()->firstOrFail()->id;

        $manager = User::factory()->create();
        signInWithBusinessRole($manager, 'branch_manager', $branch);

        $this->postJson("/api/v1/customer-change-requests/{$requestId}/decision", [
            'decision' => 'APPROVE',
        ])->assertOk()
            ->assertJsonPath('data.status', 'APROBADA')
            ->assertJsonPath('data.applied_at', now()->toIso8601String());

        $this->assertDatabaseHas('people', [
            'id' => $customer->person_id,
            'mobile_phone' => '5559998877',
            'email' => 'nuevo@example.com',
        ]);
    });

    it('rejects a change request with a reason', function (): void {
        $branch = Branch::factory()->create();
        $customer = Customer::factory()->active()->create(['branch_id' => $branch->id]);

        $cashier = User::factory()->create();
        signInWithBusinessRole($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/customers/{$customer->id}/change-requests", [
            'change_type' => 'IDENTITY',
            'new_values' => ['first_name' => 'Pedro'],
        ])->assertCreated();

        $requestId = CustomerChangeRequest::query()->firstOrFail()->id;

        $manager = User::factory()->create();
        signInWithBusinessRole($manager, 'branch_manager', $branch);

        $this->postJson("/api/v1/customer-change-requests/{$requestId}/decision", [
            'decision' => 'REJECT',
            'rejection_reason' => 'Documentacion insuficiente',
        ])->assertOk()
            ->assertJsonPath('data.status', 'RECHAZADA')
            ->assertJsonPath('data.rejection_reason', 'Documentacion insuficiente');

        $this->assertDatabaseHas('people', [
            'id' => $customer->person_id,
            'first_name' => $customer->person->first_name,
        ]);
    });
});

describe('Customer transfers', function (): void {
    it('lets the destination distributor request a transfer and the origin coordinator approve it', function (): void {
        $branch = Branch::factory()->create();
        $sourceDistributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $destinationDistributor = Distributor::factory()->create(['branch_id' => $branch->id]);

        $customer = Customer::factory()->active()->verified()->create(['branch_id' => $branch->id]);
        CustomerDistributor::query()->create([
            'distributor_id' => $sourceDistributor->id,
            'customer_id' => $customer->id,
            'relationship_status' => CustomerDistributorRelationshipStatus::ACTIVA,
        ]);

        $destinationUser = User::factory()->create();
        signInAsDistributor($destinationUser, $destinationDistributor);

        $this->postJson("/api/v1/customers/{$customer->id}/transfer-requests", [
            'destination_distributor_id' => $destinationDistributor->id,
            'request_reason' => 'El cliente se muda a nuestra zona',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'PENDIENTE_COORDINADOR')
            ->assertJsonPath('data.source_distributor_id', $sourceDistributor->id)
            ->assertJsonPath('data.destination_distributor_id', $destinationDistributor->id);

        $this->assertDatabaseHas('customer_transfer_requests', [
            'customer_id' => $customer->id,
            'status' => 'PENDIENTE_COORDINADOR',
        ]);

        $requestId = CustomerTransferRequest::query()->firstOrFail()->id;

        $coordinator = User::factory()->create();
        signInWithBusinessRole($coordinator, 'coordinator', $branch);

        $this->postJson("/api/v1/customer-transfer-requests/{$requestId}/decision", [
            'decision' => 'APPROVE',
            'comments' => 'Autorizado',
        ])->assertOk()
            ->assertJsonPath('data.status', 'EJECUTADA')
            ->assertJsonPath('data.executed_at', now()->toIso8601String());

        $this->assertDatabaseHas('customer_distributor', [
            'customer_id' => $customer->id,
            'distributor_id' => $sourceDistributor->id,
            'relationship_status' => 'TERMINADA',
        ]);
        $this->assertDatabaseHas('customer_distributor', [
            'customer_id' => $customer->id,
            'distributor_id' => $destinationDistributor->id,
            'relationship_status' => 'ACTIVA',
        ]);
    });

    it('blocks a transfer when the customer has outstanding debt', function (): void {
        $branch = Branch::factory()->create();
        $sourceDistributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $destinationDistributor = Distributor::factory()->create(['branch_id' => $branch->id]);

        $customer = Customer::factory()->active()->verified()->create(['branch_id' => $branch->id]);
        CustomerDistributor::query()->create([
            'distributor_id' => $sourceDistributor->id,
            'customer_id' => $customer->id,
            'relationship_status' => CustomerDistributorRelationshipStatus::ACTIVA,
        ]);

        $product = FinancialProduct::query()->create([
            'code' => 'QUINCENA-8',
            'name' => 'Plan quincenal 8',
            'principal_amount' => '1000.00',
            'number_of_fortnights' => 8,
            'company_commission_percentage' => '10.0000',
            'insurance_amount' => '100.00',
            'fortnightly_interest_percentage' => '5.0000',
        ]);

        Voucher::query()->create([
            'voucher_number' => 'V-0001',
            'distributor_id' => $sourceDistributor->id,
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
            'branch_id' => $branch->id,
            'status' => 'ACTIVO',
            'amount' => '1000.00',
            'total_debt_amount' => '1600.00',
            'current_balance' => '1600.00',
            'total_fortnights' => 8,
            'fortnightly_payment_amount' => '200.00',
            'payments_made' => 0,
        ]);

        $destinationUser = User::factory()->create();
        signInAsDistributor($destinationUser, $destinationDistributor);

        $this->postJson("/api/v1/customers/{$customer->id}/transfer-requests", [
            'destination_distributor_id' => $destinationDistributor->id,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'El cliente tiene saldo pendiente y no puede transferirse.');

        $this->assertDatabaseCount('customer_transfer_requests', 0);
    });

    it('lets only the requesting distributor cancel a pending transfer', function (): void {
        $branch = Branch::factory()->create();
        $sourceDistributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $destinationDistributor = Distributor::factory()->create(['branch_id' => $branch->id]);

        $customer = Customer::factory()->active()->verified()->create(['branch_id' => $branch->id]);
        CustomerDistributor::query()->create([
            'distributor_id' => $sourceDistributor->id,
            'customer_id' => $customer->id,
            'relationship_status' => CustomerDistributorRelationshipStatus::ACTIVA,
        ]);

        $destinationUser = User::factory()->create();
        signInAsDistributor($destinationUser, $destinationDistributor);

        $this->postJson("/api/v1/customers/{$customer->id}/transfer-requests", [
            'destination_distributor_id' => $destinationDistributor->id,
        ])->assertCreated();

        $requestId = CustomerTransferRequest::query()->firstOrFail()->id;

        $this->postJson("/api/v1/customer-transfer-requests/{$requestId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'CANCELADA');

        $this->assertDatabaseHas('customer_transfer_requests', [
            'id' => $requestId,
            'status' => 'CANCELADA',
        ]);
    });
});