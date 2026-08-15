<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\DistributorCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function signInWithRole(User $user, string $roleCode, Branch $branch): void
{
    $role = Role::query()->firstOrCreate(['code' => $roleCode], ['name' => $roleCode]);
    $user->businessRoles()->attach($role, [
        'branch_id' => $branch->id,
        'assigned_at' => now(),
        'is_primary' => true,
    ]);
    Sanctum::actingAs($user);
}

describe('Distributor onboarding', function (): void {
    it('moves an application from coordinator capture to approved distributor', function (): void {
        $branch = Branch::factory()->create();
        $coordinator = User::factory()->create();
        $verifier = User::factory()->create();
        $manager = User::factory()->create();
        signInWithRole($coordinator, 'coordinator', $branch);

        $application = $this->postJson('/api/v1/applications', [
            'branch_id' => $branch->id,
            'person' => [
                'first_name' => 'Ana',
                'last_name' => 'Distribuidora',
                'curp' => 'ABCD900101HNLXYZ01',
            ],
            'family_data' => ['children' => 2],
            'vehicles' => [['type' => 'car']],
            'requested_credit_limit' => '10000.00',
        ])->assertCreated()->assertJsonPath('data.status', 'EN_REVISION')->json('data');

        $this->patchJson("/api/v1/applications/{$application['id']}/verifier", [
            'verifier_user_id' => $verifier->id,
        ])->assertOk();

        signInWithRole($verifier, 'verifier', $branch);
        $this->postJson("/api/v1/applications/{$application['id']}/verification", [
            'result' => 'VERIFICADA',
            'visit_date' => now()->toDateTimeString(),
            'checklist' => ['home_visited' => true],
        ])->assertOk();

        $category = DistributorCategory::query()->create([
            'code' => 'PLATA',
            'name' => 'Plata',
            'commission_percentage' => '6.0000',
        ]);
        signInWithRole($manager, 'branch_manager', $branch);

        $this->postJson("/api/v1/applications/{$application['id']}/decision", [
            'decision' => 'APPROVE',
            'credit_limit' => '10000.00',
            'category_id' => $category->id,
            'coordinator_user_id' => $coordinator->id,
        ])->assertOk()
            ->assertJsonPath('data.application.status', 'APROBADA')
            ->assertJsonPath('data.distributor.credit_limit', '10000.00')
            ->assertJsonStructure(['data' => ['distributor_username']]);

        $this->assertDatabaseHas('distributors', [
            'branch_id' => $branch->id,
            'status' => 'ACTIVA',
            'can_issue_vouchers' => true,
        ]);
        $this->assertDatabaseHas('distributor_activations', ['used_at' => null]);
    });
});
