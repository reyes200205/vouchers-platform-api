<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\RolesAndPermissionSeeder::class);
});

function staffRole(string $code): Role
{
    return Role::query()->firstOrCreate(['code' => $code], ['name' => $code, 'guard_name' => 'web', 'is_active' => true]);
}

function staffSignIn(string $roleCode, ?Branch $branch = null): User
{
    $user = User::factory()->create();
    $user->businessRoles()->attach(staffRole($roleCode), [
        'branch_id' => $branch?->id,
        'assigned_at' => now(),
        'is_primary' => true,
    ]);
    Sanctum::actingAs($user);
    return $user;
}

describe('Staff management', function (): void {
    it('lists staff for the general manager with role filter', function (): void {
        $branch = Branch::factory()->create();
        staffSignIn('general_manager');

        $coordinator = User::factory()->create();
        $coordinator->businessRoles()->attach(staffRole('coordinator'), [
            'branch_id' => $branch->id,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);

        $this->getJson('/api/v1/staff?role=coordinator')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $coordinator->id)
            ->assertJsonPath('data.data.0.roles.0.code', 'coordinator');
    });

    it('only lists staff within branch manager scope', function (): void {
        $myBranch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        staffSignIn('branch_manager', $myBranch);

        $myCashier = User::factory()->create();
        $myCashier->businessRoles()->attach(staffRole('cashier'), [
            'branch_id' => $myBranch->id,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);

        $otherCashier = User::factory()->create();
        $otherCashier->businessRoles()->attach(staffRole('cashier'), [
            'branch_id' => $otherBranch->id,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);

        $response = $this->getJson('/api/v1/staff')->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id');
        expect($ids)->toContain($myCashier->id)
            ->and($ids)->not->toContain($otherCashier->id);
    });

    it('creates a cashier assigned to a branch as general manager', function (): void {
        $branch = Branch::factory()->create();
        staffSignIn('general_manager');

        $this->postJson('/api/v1/staff', [
            'first_name' => 'Ana',
            'middle_name' => 'Maria',
            'last_name' => 'Lopez',
            'second_last_name' => 'Garcia',
            'gender' => 'F',
            'birth_date' => '1992-05-14',
            'curp' => 'LOPA920514MDFRLN03',
            'rfc' => 'LOPA920514MDF',
            'street' => 'Av. Juarez 123',
            'external_number' => '45',
            'neighborhood' => 'Centro',
            'city' => 'Monterrey',
            'state' => 'Nuevo Leon',
            'postal_code' => '64000',
            'mobile_phone' => '8112345678',
            'email' => 'ana.lopez@correo.com',
            'username' => 'ana.lopez',
            'password' => 'secret123',
            'role_code' => 'cashier',
            'branch_id' => $branch->id,
        ])->assertCreated()
            ->assertJsonPath('data.username', 'ana.lopez')
            ->assertJsonPath('data.person.curp', 'LOPA920514MDFRLN03')
            ->assertJsonPath('data.person.rfc', 'LOPA920514MDF')
            ->assertJsonPath('data.person.second_last_name', 'Garcia')
            ->assertJsonPath('data.person.street', 'Av. Juarez 123')
            ->assertJsonPath('data.person.postal_code', '64000')
            ->assertJsonPath('data.roles.0.code', 'cashier')
            ->assertJsonPath('data.roles.0.branch_id', $branch->id)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('users', ['username' => 'ana.lopez', 'is_active' => true]);
        $this->assertDatabaseHas('people', ['curp' => 'LOPA920514MDFRLN03']);
    });

    it('rejects creating staff without a valid CURP', function (): void {
        $branch = Branch::factory()->create();
        staffSignIn('general_manager');

        $this->postJson('/api/v1/staff', [
            'first_name' => 'Luis',
            'last_name' => 'Perez',
            'username' => 'luis.perez',
            'password' => 'secret123',
            'role_code' => 'cashier',
            'branch_id' => $branch->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('curp');
    });

    it('rejects creating a non-staff role', function (): void {
        $branch = Branch::factory()->create();
        staffSignIn('general_manager');

        $this->postJson('/api/v1/staff', [
            'first_name' => 'Jose',
            'last_name' => 'Perez',
            'username' => 'jose.perez',
            'password' => 'secret123',
            'curp' => 'PEPS921201MNERRR05',
            'role_code' => 'administrator',
            'branch_id' => $branch->id,
        ])->assertStatus(422);
    });

    it('lets a branch manager create a cashier, coordinator, and verifier in their branch', function (): void {
        $myBranch = Branch::factory()->create();
        staffSignIn('branch_manager', $myBranch);

        $this->postJson('/api/v1/staff', [
            'first_name' => 'Luis',
            'last_name' => 'Gomez',
            'username' => 'luis.gomez',
            'password' => 'secret123',
            'curp' => 'GOLL920515MNLMRS04',
            'role_code' => 'cashier',
            'branch_id' => $myBranch->id,
        ])->assertCreated();

        $otherBranch = Branch::factory()->create();
        $this->postJson('/api/v1/staff', [
            'first_name' => 'Rosa',
            'last_name' => 'Diaz',
            'username' => 'rosa.diaz',
            'password' => 'secret123',
            'curp' => 'DILR930606JDFRDC08',
            'role_code' => 'cashier',
            'branch_id' => $otherBranch->id,
        ])->assertStatus(403);

        $this->postJson('/api/v1/staff', [
            'first_name' => 'Ivan',
            'last_name' => 'Castro',
            'username' => 'ivan.castro',
            'password' => 'secret123',
            'curp' => 'CASI940707HDFTRV02',
            'role_code' => 'coordinator',
            'branch_id' => $myBranch->id,
        ])->assertCreated();
    });

    it('lets a branch manager create a verifier for their branch', function (): void {
        $myBranch = Branch::factory()->create();
        staffSignIn('branch_manager', $myBranch);

        $this->postJson('/api/v1/staff', [
            'first_name' => 'Maria',
            'last_name' => 'Lopez',
            'username' => 'maria.lopez',
            'password' => 'secret123',
            'curp' => 'LOPM920514MDFRRR06',
            'role_code' => 'verifier',
            'branch_id' => $myBranch->id,
        ])->assertCreated();
    });

    it('lets a branch manager upgrade an existing cashier to coordinator or verifier', function (): void {
        $myBranch = Branch::factory()->create();
        staffSignIn('branch_manager', $myBranch);

        $cashier = User::factory()->create();
        $cashier->businessRoles()->attach(staffRole('cashier'), [
            'branch_id' => $myBranch->id,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);

        $this->patchJson("/api/v1/staff/{$cashier->id}", [
            'is_active' => true,
            'role_code' => 'coordinator',
        ])->assertOk()
            ->assertJsonPath('data.roles.0.code', 'coordinator');

        $this->patchJson("/api/v1/staff/{$cashier->id}", [
            'is_active' => true,
            'role_code' => 'verifier',
        ])->assertOk()
            ->assertJsonPath('data.roles.0.code', 'verifier');
    });

    it('updates staff status and role as general manager', function (): void {
        $branch = Branch::factory()->create();
        staffSignIn('general_manager');

        $cashier = User::factory()->create();
        $cashier->businessRoles()->attach(staffRole('cashier'), [
            'branch_id' => $branch->id,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);

        $this->patchJson("/api/v1/staff/{$cashier->id}", [
            'is_active' => false,
            'role_code' => 'verifier',
        ])->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.roles.0.code', 'verifier');

        $this->patchJson("/api/v1/staff/{$cashier->id}", [
            'is_active' => true,
            'role_code' => 'cashier',
        ])->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.roles.0.code', 'cashier');
    });

    it('updates status only (no role fields) without errors', function (): void {
        $branch = Branch::factory()->create();
        staffSignIn('general_manager');

        $cashier = User::factory()->create();
        $cashier->businessRoles()->attach(staffRole('cashier'), [
            'branch_id' => $branch->id,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);

        $this->patchJson("/api/v1/staff/{$cashier->id}", [
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.roles.0.code', 'cashier');
    });

    it('updates staff person data as general manager', function (): void {
        $branch = Branch::factory()->create();
        staffSignIn('general_manager');

        $cashier = User::factory()->create();
        $person = App\Models\Person::factory()->create([
            'first_name' => 'Viejo',
            'last_name' => 'Nombre',
        ]);
        $cashier->person_id = $person->id;
        $cashier->save();
        $cashier->businessRoles()->attach(staffRole('cashier'), [
            'branch_id' => $branch->id,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);

        $this->patchJson("/api/v1/staff/{$cashier->id}", [
            'is_active' => true,
            'first_name' => 'Nuevo',
            'last_name' => 'Apellido',
            'curp' => 'NUAP920515MDFPLN04',
            'street' => 'Calle Nueva 1',
        ])->assertOk()
            ->assertJsonPath('data.person.first_name', 'Nuevo')
            ->assertJsonPath('data.person.last_name', 'Apellido')
            ->assertJsonPath('data.person.curp', 'NUAP920515MDFPLN04')
            ->assertJsonPath('data.person.street', 'Calle Nueva 1');
    });

    it('forbids non-staff abilities', function (): void {
        $user = User::factory()->create();
        $user->businessRoles()->attach(staffRole('cashier'), [
            'branch_id' => null,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/staff')->assertStatus(403);
        $this->postJson('/api/v1/staff', [
            'first_name' => 'X',
            'last_name' => 'Y',
            'username' => 'x.y',
            'password' => 'secret123',
            'curp' => 'XYXY000101MXLYYN00',
            'role_code' => 'cashier',
            'branch_id' => Branch::factory()->create()->id,
        ])->assertStatus(403);
    });
});
