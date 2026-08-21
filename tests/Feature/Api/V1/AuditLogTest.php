<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

if (! function_exists('actingAsBusinessRole')) {
    function actingAsBusinessRole(User $user, string $roleCode): void
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleCode, 'guard_name' => 'web'],
            ['code' => $roleCode]
        );

        $user->businessRoles()->attach($role, [
            'assigned_at' => now(),
            'is_primary' => true,
        ]);

        Sanctum::actingAs($user);
    }
}

describe('Audit Logs API', function (): void {
    it('allows a super-admin to view audit logs', function (): void {
        $superAdmin = User::factory()->create();
        actingAsBusinessRole($superAdmin, 'super-admin');

        AuditLog::query()->create([
            'event_type' => 'TEST_EVENT',
            'level' => 'info',
            'user_name' => 'testuser',
            'module' => 'tests',
            'description' => 'Test log description',
        ]);

        $this->getJson('/api/v1/system/audit-logs')
            ->assertOk()
            ->assertJsonPath('data.data.0.event_type', 'TEST_EVENT');
    });

    it('allows a super-admin to filter audit logs by search, level, and module', function (): void {
        $superAdmin = User::factory()->create();
        actingAsBusinessRole($superAdmin, 'super-admin');

        AuditLog::query()->create([
            'event_type' => 'LOGIN',
            'level' => 'info',
            'user_name' => 'alice',
            'module' => 'auth',
            'description' => 'User logged in',
        ]);

        AuditLog::query()->create([
            'event_type' => 'VOUCHER_CREATE',
            'level' => 'warning',
            'user_name' => 'bob',
            'module' => 'vouchers',
            'description' => 'Voucher created with override',
        ]);

        // Filter by level
        $this->getJson('/api/v1/system/audit-logs?level=warning')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.event_type', 'VOUCHER_CREATE');

        // Filter by module
        $this->getJson('/api/v1/system/audit-logs?module=auth')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.event_type', 'LOGIN');

        // Filter by search
        $this->getJson('/api/v1/system/audit-logs?search=bob')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.event_type', 'VOUCHER_CREATE');
    });

    it('forbids other roles from viewing audit logs', function (): void {
        $manager = User::factory()->create();
        actingAsBusinessRole($manager, 'general_manager');

        $this->getJson('/api/v1/system/audit-logs')
            ->assertForbidden();
    });
});

describe('Super Admin Additional Features', function (): void {
    it('allows a super-admin to view staff list', function (): void {
        $superAdmin = User::factory()->create();
        actingAsBusinessRole($superAdmin, 'super-admin');

        $this->getJson('/api/v1/staff')
            ->assertOk();
    });

    it('allows a super-admin to create a general manager', function (): void {
        $superAdmin = User::factory()->create();
        actingAsBusinessRole($superAdmin, 'super-admin');

        $this->postJson('/api/v1/general-managers', [
            'first_name' => 'Alessandro',
            'last_name' => 'Demo',
            'username' => 'alessandro.manager',
            'password' => 'supersecret123',
        ])->assertCreated();

        $this->assertDatabaseHas('users', [
            'username' => 'alessandro.manager',
        ]);
    });
});
