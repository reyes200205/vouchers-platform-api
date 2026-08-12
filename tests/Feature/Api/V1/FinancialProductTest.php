<?php

declare(strict_types=1);

use App\Models\FinancialProduct;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function authenticateWithBusinessRole(User $user, string $roleCode): void
{
    $role = Role::query()->create([
        'code' => $roleCode,
        'name' => str($roleCode)->replace('_', ' ')->title(),
    ]);

    $user->businessRoles()->attach($role, [
        'assigned_at' => now(),
        'is_primary' => true,
    ]);

    Sanctum::actingAs($user);
}

function financialProductPayload(): array
{
    return [
        'code' => 'QUINCENA-12',
        'name' => 'Plan quincenal 12',
        'description' => 'Plan de prueba',
        'principal_amount' => '5000.00',
        'number_of_fortnights' => 12,
        'company_commission_percentage' => '5.0000',
        'insurance_amount' => '100.00',
        'fortnightly_interest_percentage' => '2.5000',
        'late_fee_amount' => '50.00',
        'disbursement_method' => 'TRANSFERENCIA',
        'is_active' => true,
    ];
}

describe('Financial products', function (): void {
    it('allows a general manager to create and disable a voucher plan', function (): void {
        $manager = User::factory()->create();
        authenticateWithBusinessRole($manager, 'general_manager');

        $this->postJson('/api/v1/financial-products', financialProductPayload())
            ->assertCreated()
            ->assertJsonPath('data.code', 'QUINCENA-12')
            ->assertJsonPath('data.is_active', true);

        $product = FinancialProduct::query()->firstOrFail();

        $this->patchJson("/api/v1/financial-products/{$product->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('financial_products', [
            'id' => $product->id,
            'is_active' => false,
        ]);
    });

    it('allows an administrator to view plans but not manage them', function (): void {
        $administrator = User::factory()->create();
        authenticateWithBusinessRole($administrator, 'administrator');
        $product = FinancialProduct::query()->create(financialProductPayload());

        $this->getJson('/api/v1/financial-products')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $product->id);

        $this->postJson('/api/v1/financial-products', array_merge(financialProductPayload(), [
            'code' => 'QUINCENA-24',
        ]))->assertForbidden();
    });
});
