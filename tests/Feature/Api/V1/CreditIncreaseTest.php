<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\CreditIncreaseRequest;
use App\Models\Distributor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function creditSignInBusinessRole(User $user, string $roleCode, Branch $branch): void
{
    $role = Role::query()->firstOrCreate(['code' => $roleCode], ['name' => $roleCode]);
    $user->businessRoles()->attach($role, [
        'branch_id' => $branch->id,
        'assigned_at' => now(),
        'is_primary' => true,
    ]);
    Sanctum::actingAs($user);
}

function creditSignInAsDistributor(User $user, Distributor $distributor): void
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

describe('Credit increase requests', function (): void {
    it('lets a distributor request a credit increase and the coordinator pre-authorize it', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'credit_limit' => 20000,
            'available_credit' => 20000,
        ]);

        $distributorUser = User::factory()->create();
        creditSignInAsDistributor($distributorUser, $distributor);

        $this->postJson('/api/v1/credit-increase-requests', [
            'distributor_id' => $distributor->id,
            'requested_amount' => '10000.00',
            'reason' => 'Crecimiento de cartera',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'PENDIENTE')
            ->assertJsonPath('data.requested_amount', '10000.00');

        $request = CreditIncreaseRequest::query()->firstOrFail();

        $coordinator = User::factory()->create();
        creditSignInBusinessRole($coordinator, 'coordinator', $branch);

        $this->postJson("/api/v1/credit-increase-requests/{$request->id}/pre-authorize", [
            'pre_authorized_amount' => '8000.00',
        ])->assertOk()
            ->assertJsonPath('data.status', 'PRE_AUTORIZADO')
            ->assertJsonPath('data.pre_authorized_amount', '8000.00')
            ->assertJsonPath('data.pre_authorized_by_user_id', $coordinator->id);
    });

    it('lets the general manager approve and applies the credit to the distributor', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'credit_limit' => 20000,
            'available_credit' => 20000,
        ]);

        $request = CreditIncreaseRequest::factory()->create([
            'distributor_id' => $distributor->id,
            'branch_id' => $branch->id,
            'requested_amount' => 10000.00,
            'status' => 'PRE_AUTORIZADO',
            'pre_authorized_amount' => 8000.00,
        ]);

        $manager = User::factory()->create();
        creditSignInBusinessRole($manager, 'general_manager', $branch);

        $this->postJson("/api/v1/credit-increase-requests/{$request->id}/decision", [
            'decision' => 'APROBADO',
        ])->assertOk()
            ->assertJsonPath('data.status', 'APROBADO')
            ->assertJsonPath('data.approved_amount', '8000.00');

        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'credit_limit' => 28000.00,
            'available_credit' => 28000.00,
        ]);

        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
        ]);
        expect($distributor->refresh()->prevale_required_after_credit_increase_at)->not->toBeNull();
    });

    it('applies only the reduced amount when the manager reduces the increase', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'credit_limit' => 20000,
            'available_credit' => 20000,
        ]);

        $request = CreditIncreaseRequest::factory()->create([
            'distributor_id' => $distributor->id,
            'branch_id' => $branch->id,
            'requested_amount' => 10000.00,
            'status' => 'PRE_AUTORIZADO',
            'pre_authorized_amount' => 8000.00,
        ]);

        $manager = User::factory()->create();
        creditSignInBusinessRole($manager, 'general_manager', $branch);

        $this->postJson("/api/v1/credit-increase-requests/{$request->id}/decision", [
            'decision' => 'REDUCIDO',
            'approved_amount' => '5000.00',
        ])->assertOk()
            ->assertJsonPath('data.status', 'REDUCIDO')
            ->assertJsonPath('data.approved_amount', '5000.00');

        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'credit_limit' => 25000.00,
            'available_credit' => 25000.00,
        ]);
    });

    it('does not touch the credit when the request is rejected', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'credit_limit' => 20000,
            'available_credit' => 20000,
        ]);

        $request = CreditIncreaseRequest::factory()->create([
            'distributor_id' => $distributor->id,
            'branch_id' => $branch->id,
            'requested_amount' => 10000.00,
            'status' => 'PRE_AUTORIZADO',
            'pre_authorized_amount' => 8000.00,
        ]);

        $manager = User::factory()->create();
        creditSignInBusinessRole($manager, 'general_manager', $branch);

        $this->postJson("/api/v1/credit-increase-requests/{$request->id}/decision", [
            'decision' => 'RECHAZADO',
            'decision_notes' => 'No hay fondeo',
        ])->assertOk()
            ->assertJsonPath('data.status', 'RECHAZADO');

        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'credit_limit' => 20000.00,
            'available_credit' => 20000.00,
        ]);
    });

    it('forbids the distributor from deciding a credit increase', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $request = CreditIncreaseRequest::factory()->create([
            'distributor_id' => $distributor->id,
            'branch_id' => $branch->id,
            'requested_amount' => 10000.00,
            'status' => 'PRE_AUTORIZADO',
            'pre_authorized_amount' => 8000.00,
        ]);

        $distributorUser = User::factory()->create();
        creditSignInAsDistributor($distributorUser, $distributor);

        $this->postJson("/api/v1/credit-increase-requests/{$request->id}/decision", [
            'decision' => 'APROBADO',
        ])->assertForbidden();
    });

    it('rejects a decision before the coordinator pre-authorizes', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $request = CreditIncreaseRequest::factory()->create([
            'distributor_id' => $distributor->id,
            'branch_id' => $branch->id,
            'requested_amount' => 10000.00,
            'status' => 'PENDIENTE',
        ]);

        $manager = User::factory()->create();
        creditSignInBusinessRole($manager, 'general_manager', $branch);

        $this->postJson("/api/v1/credit-increase-requests/{$request->id}/decision", [
            'decision' => 'APROBADO',
        ])->assertStatus(422);
    });
});