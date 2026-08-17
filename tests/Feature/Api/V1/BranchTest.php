<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function actingAsBusinessRole(User $user, string $roleCode): void
{
    $role = Role::query()->create([
        'code' => $roleCode,
        'name' => str($roleCode)->replace('_', ' ')->title(),
    ]);

    $user->update(['role_id' => $role->id]);

    Sanctum::actingAs($user);
}

describe('Branches', function (): void {
    it('allows a general manager to create a branch and configure its cutoff settings', function (): void {
        $manager = User::factory()->create();
        actingAsBusinessRole($manager, 'general_manager');

        $branch = $this->postJson('/api/v1/branches', [
            'code' => 'MTY-01',
            'name' => 'Sucursal Monterrey',
            'address' => 'Av. Constitucion 123',
            'phone' => '8180000000',
        ])->assertCreated()->json('data');

        $this->patchJson("/api/v1/branches/{$branch['id']}/settings", [
            'cutoff_day' => 15,
            'cutoff_time' => '17:30',
            'payment_frequency_days' => 14,
            'default_credit_limit' => '10000.00',
            'biweekly_interest_percentage' => '5.0000',
        ])->assertOk()->assertJsonPath('data.cutoff_day', 15);

        $this->assertDatabaseHas('branch_settings_logs', [
            'branch_id' => $branch['id'],
            'updated_by_user_id' => $manager->id,
        ]);
    });

    it('allows an administrator to view but not modify a branch', function (): void {
        $administrator = User::factory()->create();
        actingAsBusinessRole($administrator, 'administrator');
        $branch = Branch::factory()->create();

        $this->getJson("/api/v1/branches/{$branch->id}")->assertOk();
        $this->patchJson("/api/v1/branches/{$branch->id}", ['name' => 'No permitido'])->assertForbidden();
    });
});
