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

function signInFromIp(User $user, string $roleCode, ?Branch $branch, string $ip): void
{
    $role = Role::query()->firstOrCreate(['code' => $roleCode], ['name' => $roleCode]);
    $user->businessRoles()->attach($role, [
        'branch_id' => $branch?->id,
        'assigned_at' => now(),
        'is_primary' => true,
    ]);
    Sanctum::actingAs($user);
    test()->withServerVariables(['REMOTE_ADDR' => $ip]);
}

describe('VPN-restricted approval endpoints', function (): void {
    it('lets a general manager into the inbox from an IP inside the configured VPN range', function (): void {
        config()->set('network.vpn_cidrs', ['192.168.10.0/24']);

        $manager = User::factory()->create();
        signInFromIp($manager, 'general_manager', null, '192.168.10.2');

        $this->getJson('/api/v1/general/inbox')->assertOk();
    });

    it('forbids a general manager from the inbox when the IP is outside the VPN range', function (): void {
        config()->set('network.vpn_cidrs', ['192.168.10.0/24']);

        $manager = User::factory()->create();
        signInFromIp($manager, 'general_manager', null, '203.0.113.5');

        $this->getJson('/api/v1/general/inbox')->assertForbidden();
    });

    it('forbids a branch manager from deciding a credit increase request outside the VPN range', function (): void {
        config()->set('network.vpn_cidrs', ['192.168.10.0/24']);

        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $request = CreditIncreaseRequest::factory()->create([
            'distributor_id' => $distributor->id,
            'branch_id' => $branch->id,
            'status' => 'PRE_AUTORIZADO',
        ]);

        $manager = User::factory()->create();
        signInFromIp($manager, 'branch_manager', $branch, '203.0.113.5');

        $this->postJson("/api/v1/credit-increase-requests/{$request->id}/decision", [
            'decision' => 'APPROVED',
        ])->assertForbidden();
    });

    it('denies access by default when no VPN range is configured, even from a plausible-looking IP', function (): void {
        config()->set('network.vpn_cidrs', []);

        $manager = User::factory()->create();
        signInFromIp($manager, 'general_manager', null, '192.168.10.2');

        $this->getJson('/api/v1/general/inbox')->assertForbidden();
    });

    it('does not restrict a coordinator deciding a customer transfer, regardless of IP', function (): void {
        config()->set('network.vpn_cidrs', ['192.168.10.0/24']);

        $branch = Branch::factory()->create();
        $coordinator = User::factory()->create();
        signInFromIp($coordinator, 'coordinator', $branch, '203.0.113.5');

        // No existe la solicitud, pero debe llegar hasta el model binding
        // (404) en vez de quedarse en el 403 de vpn.restrict — confirma que
        // el middleware lo dejó pasar por no ser un rol restringido.
        $this->postJson('/api/v1/customer-transfer-requests/999999/decision', [
            'decision' => 'APPROVED',
        ])->assertNotFound();
    });
});
