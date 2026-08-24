<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(Database\Seeders\RolesAndPermissionSeeder::class);
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

function getValidStaffPayload(array $overrides = []): array
{
    $suffix = strtoupper(Str::random(5));
    $usernameSuffix = Str::random(5);
    return array_merge([
        'first_name' => 'Luis',
        'last_name' => 'Gomez',
        'second_last_name' => 'Materno',
        'gender' => 'M',
        'birth_date' => '1990-01-01',
        'curp' => 'GOLL900101MNL' . substr($suffix, 0, 5),
        'rfc' => 'GOLL900101' . substr($suffix, 0, 3),
        'mobile_phone' => '8711234567',
        'email' => "luis.gomez.{$usernameSuffix}@gmail.com",
        'street' => 'Calle 123',
        'external_number' => '100',
        'neighborhood' => 'Centro',
        'city' => 'Torreon',
        'state' => 'Coahuila',
        'postal_code' => '27000',
        'username' => "luis.gomez.{$usernameSuffix}",
        'password' => 'secret123',
        'role_code' => 'cashier',
        'branch_id' => 1,
    ], $overrides);
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

    it('shows a single staff member for the general manager', function (): void {
        $branch = Branch::factory()->create();
        staffSignIn('general_manager');

        $cashier = User::factory()->create();
        $cashier->businessRoles()->attach(staffRole('cashier'), [
            'branch_id' => $branch->id,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);

        $this->getJson("/api/v1/staff/{$cashier->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $cashier->id)
            ->assertJsonPath('data.roles.0.code', 'cashier');
    });

    it('lets a branch manager show a staff member within their own branch', function (): void {
        $myBranch = Branch::factory()->create();
        staffSignIn('branch_manager', $myBranch);

        $myCashier = User::factory()->create();
        $myCashier->businessRoles()->attach(staffRole('cashier'), [
            'branch_id' => $myBranch->id,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);

        $this->getJson("/api/v1/staff/{$myCashier->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $myCashier->id);
    });

    it('forbids a branch manager from showing staff outside their branch', function (): void {
        $myBranch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        staffSignIn('branch_manager', $myBranch);

        $otherCashier = User::factory()->create();
        $otherCashier->businessRoles()->attach(staffRole('cashier'), [
            'branch_id' => $otherBranch->id,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);

        $this->getJson("/api/v1/staff/{$otherCashier->id}")
            ->assertStatus(403);
    });

    it('returns 404 when the target user is not a staff role', function (): void {
        staffSignIn('general_manager');

        $distributor = User::factory()->create();
        $distributor->businessRoles()->attach(staffRole('distributor'), [
            'assigned_at' => now(),
            'is_primary' => true,
        ]);

        $this->getJson("/api/v1/staff/{$distributor->id}")
            ->assertStatus(404);
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

    it('rejects creating a branch manager when the branch already has an active one', function (): void {
        $branch = Branch::factory()->create();
        staffSignIn('general_manager');

        $existingManager = User::factory()->create();
        $existingManager->businessRoles()->attach(staffRole('branch_manager'), [
            'branch_id' => $branch->id,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);

        $this->postJson('/api/v1/staff', getValidStaffPayload([
            'curp' => 'GOME900101MNLXXX01',
            'role_code' => 'branch_manager',
            'branch_id' => $branch->id,
        ]))->assertStatus(422);
    });

    it('rejects switching a staff member to branch_manager when the branch already has an active one', function (): void {
        $branch = Branch::factory()->create();
        staffSignIn('general_manager');

        $existingManager = User::factory()->create();
        $existingManager->businessRoles()->attach(staffRole('branch_manager'), [
            'branch_id' => $branch->id,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);

        $cashier = User::factory()->create();
        $cashier->businessRoles()->attach(staffRole('cashier'), [
            'branch_id' => $branch->id,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);

        $this->patchJson("/api/v1/staff/{$cashier->id}", [
            'is_active' => true,
            'role_code' => 'branch_manager',
            'branch_id' => $branch->id,
        ])->assertStatus(422);

        // El gerente existente sigue activo y el aspirante sigue como cajero: nada cambio.
        $this->assertDatabaseHas('model_has_roles', [
            'model_id' => $existingManager->id,
            'branch_id' => $branch->id,
            'revoked_at' => null,
        ]);
        $this->assertDatabaseHas('model_has_roles', [
            'model_id' => $cashier->id,
            'branch_id' => $branch->id,
            'revoked_at' => null,
        ]);
    });

    it('rejects creating staff without a valid CURP', function (): void {
        $branch = Branch::factory()->create();
        staffSignIn('general_manager');

        $this->postJson('/api/v1/staff', getValidStaffPayload([
            'first_name' => 'Luis',
            'last_name' => 'Perez',
            'username' => 'luis.perez',
            'curp' => '',
            'branch_id' => $branch->id,
        ]))->assertStatus(422)
            ->assertJsonValidationErrors('curp');
    });

    it('rejects creating a non-staff role', function (): void {
        $branch = Branch::factory()->create();
        staffSignIn('general_manager');

        $this->postJson('/api/v1/staff', getValidStaffPayload([
            'first_name' => 'Jose',
            'last_name' => 'Perez',
            'username' => 'jose.perez',
            'curp' => 'PEPS921201MNERRR05',
            'role_code' => 'super-admin',
            'branch_id' => $branch->id,
        ]))->assertStatus(422);
    });

    it('lets a branch manager create a cashier, coordinator, and verifier in their branch', function (): void {
        $myBranch = Branch::factory()->create();
        staffSignIn('branch_manager', $myBranch);

        $this->postJson('/api/v1/staff', getValidStaffPayload([
            'first_name' => 'Luis',
            'last_name' => 'Gomez',
            'username' => 'luis.gomez',
            'curp' => 'GOLL920515MNLMRS04',
            'role_code' => 'cashier',
            'branch_id' => $myBranch->id,
        ]))->assertCreated();

        $otherBranch = Branch::factory()->create();
        $this->postJson('/api/v1/staff', getValidStaffPayload([
            'first_name' => 'Rosa',
            'last_name' => 'Diaz',
            'username' => 'rosa.diaz',
            'curp' => 'DILR930606MDFRDC08',
            'role_code' => 'cashier',
            'branch_id' => $otherBranch->id,
        ]))->assertStatus(403);

        $this->postJson('/api/v1/staff', getValidStaffPayload([
            'first_name' => 'Ivan',
            'last_name' => 'Castro',
            'username' => 'ivan.castro',
            'curp' => 'CASI940707HDFTRV02',
            'role_code' => 'coordinator',
            'branch_id' => $myBranch->id,
        ]))->assertCreated();
    });

    it('lets a branch manager create a verifier for their branch', function (): void {
        $myBranch = Branch::factory()->create();
        staffSignIn('branch_manager', $myBranch);

        $this->postJson('/api/v1/staff', getValidStaffPayload([
            'first_name' => 'Maria',
            'last_name' => 'Lopez',
            'username' => 'maria.lopez',
            'curp' => 'LOPM920514MDFRRR06',
            'role_code' => 'verifier',
            'branch_id' => $myBranch->id,
        ]))->assertCreated();
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

    it('reuses an existing model_has_roles row created outside the staff flow instead of crashing on duplicate key', function (): void {
        // Reproduce el bug: BranchController asigna 'branch_manager' via Spatie::assignRole(),
        // lo que deja una fila activa (revoked_at null, is_primary false) en model_has_roles
        // que UpdateStaffService no conocia al buscar solo filas revocadas para reutilizar.
        $branch = Branch::factory()->create();
        staffSignIn('general_manager');

        $cashier = User::factory()->create();
        $cashier->businessRoles()->attach(staffRole('cashier'), [
            'branch_id' => $branch->id,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);

        // Fila "huerfana" para branch_manager en la misma sucursal, como la que dejaba
        // BranchController::update() al usar assignRole() directamente.
        $cashier->businessRoles()->attach(staffRole('branch_manager'), [
            'branch_id' => $branch->id,
            'assigned_at' => now(),
            'is_primary' => false,
        ]);

        $this->patchJson("/api/v1/staff/{$cashier->id}", [
            'is_active' => true,
            'role_code' => 'branch_manager',
        ])->assertOk()
            ->assertJsonPath('data.roles.0.code', 'branch_manager');
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

    it('revokes tokens and blocks requests when staff is deactivated', function (): void {
        $branch = Branch::factory()->create();
        staffSignIn('general_manager');

        $staffUser = User::factory()->create(['is_active' => true]);
        $staffUser->businessRoles()->attach(staffRole('cashier'), [
            'branch_id' => $branch->id,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);
        $token = $staffUser->createToken('staff-token')->plainTextToken;

        $this->patchJson("/api/v1/staff/{$staffUser->id}", [
            'is_active' => false,
        ])->assertOk();

        expect($staffUser->tokens()->count())->toBe(0);

        $this->app['auth']->forgetGuards();

        // 1. Con el token eliminado, Sanctum rechaza con Unauthenticated
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);

        $staffUser->refresh();
        $this->actingAs($staffUser);
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJson(['success' => false, 'message' => 'Cuenta desactivada. Ponte en contacto con un administrador.']);
    });

    it('prevents branch manager from seeing or modifying themselves in staff module', function (): void {
        $branch = Branch::factory()->create();
        $bm = staffSignIn('branch_manager', $branch);

        $cashier = User::factory()->create();
        $cashier->businessRoles()->attach(staffRole('cashier'), [
            'branch_id' => $branch->id,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);

        // 1. El branch manager no se ve a si mismo en la lista
        $response = $this->getJson('/api/v1/staff')->assertOk();
        $ids = collect($response->json('data.data'))->pluck('id')->all();
        expect($ids)->toContain($cashier->id)
            ->and($ids)->not->toContain($bm->id);

        // 2. El branch manager no puede editar su propia cuenta desde el modulo
        $this->patchJson("/api/v1/staff/{$bm->id}", [
            'is_active' => true,
            'role_code' => 'cashier',
        ])->assertStatus(403);
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
        $this->postJson('/api/v1/staff', getValidStaffPayload([
            'first_name' => 'X',
            'last_name' => 'Y',
            'username' => 'x.y',
            'curp' => 'XYXY000101MXLYYN00',
            'role_code' => 'cashier',
            'branch_id' => Branch::factory()->create()->id,
        ]))->assertStatus(403);
    });

    it('allows only super-admin to create a general manager', function (): void {
        // 1. Super-admin can create it
        $superAdmin = User::factory()->create();
        $superAdmin->businessRoles()->attach(staffRole('super-admin'), [
            'branch_id' => null,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);
        Sanctum::actingAs($superAdmin);

        $response = $this->postJson('/api/v1/staff', getValidStaffPayload([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'john.gm',
            'curp' => 'DOEJ900101MDFRND01',
            'role_code' => 'general_manager',
            'branch_id' => null,
        ]))->assertCreated();

        $newGmId = $response->json('data.id');
        $newGm = User::findOrFail($newGmId);
        expect($newGm->isGeneralManager())->toBeTrue()
            ->and($newGm->businessRoles()->first()->pivot->branch_id)->toBeNull();

        // 2. A general manager cannot create another general manager
        $gm = User::factory()->create();
        $gm->businessRoles()->attach(staffRole('general_manager'), [
            'branch_id' => null,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);
        Sanctum::actingAs($gm);

        $this->postJson('/api/v1/staff', getValidStaffPayload([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'username' => 'jane.gm',
            'curp' => 'DOEJ900101MDFRND02',
            'role_code' => 'general_manager',
            'branch_id' => null,
        ]))->assertStatus(403);
    });

    it('lets a general manager be assigned a home branch without losing global access', function (): void {
        $matriz = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();

        $superAdmin = User::factory()->create();
        $superAdmin->businessRoles()->attach(staffRole('super-admin'), [
            'branch_id' => null,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);
        Sanctum::actingAs($superAdmin);

        $response = $this->postJson('/api/v1/staff', getValidStaffPayload([
            'first_name' => 'Carla',
            'last_name' => 'Ruiz',
            'username' => 'carla.gm',
            'curp' => 'RUIC900101MDFRLL03',
            'role_code' => 'general_manager',
            'branch_id' => $matriz->id,
        ]))->assertCreated()
            ->assertJsonPath('data.home_branch.id', $matriz->id);

        $newGm = User::findOrFail($response->json('data.id'));

        // El pivot de permisos sigue siendo global (branch_id null): home_branch_id
        // es puramente informativo y no debe limitar su alcance.
        expect($newGm->home_branch_id)->toBe($matriz->id)
            ->and($newGm->businessRoles()->first()->pivot->branch_id)->toBeNull()
            ->and($newGm->hasBusinessAbility('staff.manage', $otherBranch->id))
            ->toBe($newGm->hasBusinessAbility('staff.manage', $matriz->id));
    });

    it('rejects assigning a general manager to a branch that already has an active branch manager', function (): void {
        $branch = Branch::factory()->create();

        $existingManager = User::factory()->create();
        $existingManager->businessRoles()->attach(staffRole('branch_manager'), [
            'branch_id' => $branch->id,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);

        $superAdmin = User::factory()->create();
        $superAdmin->businessRoles()->attach(staffRole('super-admin'), [
            'branch_id' => null,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);
        Sanctum::actingAs($superAdmin);

        $this->postJson('/api/v1/staff', getValidStaffPayload([
            'first_name' => 'Mario',
            'last_name' => 'Diaz',
            'username' => 'mario.gm',
            'curp' => 'DIAM900101MDFZRL04',
            'role_code' => 'general_manager',
            'branch_id' => $branch->id,
        ]))->assertStatus(422);
    });

    it('rejects assigning a second general manager to a branch another general manager already calls home', function (): void {
        // Reproduce el bug reportado: se crea "Sucursal UTT" sin gerente, se
        // crea un gerente general con UTT como sucursal base (sin problema),
        // y al crear un SEGUNDO gerente general con la misma sucursal base
        // debe bloquearse -- antes no habia ninguna validacion cruzada entre
        // gerentes generales, solo contra branch_manager.
        $utt = Branch::factory()->create();

        $superAdmin = User::factory()->create();
        $superAdmin->businessRoles()->attach(staffRole('super-admin'), [
            'branch_id' => null,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);
        Sanctum::actingAs($superAdmin);

        $this->postJson('/api/v1/staff', getValidStaffPayload([
            'first_name' => 'Jorge',
            'last_name' => 'Primero',
            'username' => 'jorge.gm1',
            'curp' => 'PRIJ900101MDFRLM05',
            'role_code' => 'general_manager',
            'branch_id' => $utt->id,
        ]))->assertCreated();

        $this->postJson('/api/v1/staff', getValidStaffPayload([
            'first_name' => 'Segundo',
            'last_name' => 'Gerente',
            'username' => 'segundo.gm2',
            'curp' => 'GERS900101MDFRLM06',
            'role_code' => 'general_manager',
            'branch_id' => $utt->id,
        ]))->assertStatus(422);
    });

    it('lets a super-admin change and clear a general manager\'s home branch', function (): void {
        $matriz = Branch::factory()->create();
        $other = Branch::factory()->create();

        $gm = User::factory()->create(['home_branch_id' => $matriz->id]);
        $gm->businessRoles()->attach(staffRole('general_manager'), [
            'branch_id' => null,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);

        $superAdmin = User::factory()->create();
        $superAdmin->businessRoles()->attach(staffRole('super-admin'), [
            'branch_id' => null,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);
        Sanctum::actingAs($superAdmin);

        // Cambiar la sucursal base (rol sin cambios).
        $this->patchJson("/api/v1/staff/{$gm->id}", [
            'is_active' => true,
            'role_code' => 'general_manager',
            'branch_id' => $other->id,
        ])->assertOk()->assertJsonPath('data.home_branch.id', $other->id);

        $this->assertDatabaseHas('users', ['id' => $gm->id, 'home_branch_id' => $other->id]);
        // El pivot de permisos nunca se toca: sigue global.
        $this->assertDatabaseHas('model_has_roles', [
            'model_id' => $gm->id,
            'branch_id' => null,
            'revoked_at' => null,
        ]);

        // Quitarle la sucursal base (branch_id null explicito).
        $this->patchJson("/api/v1/staff/{$gm->id}", [
            'is_active' => true,
            'role_code' => 'general_manager',
            'branch_id' => null,
        ])->assertOk()->assertJsonPath('data.home_branch', null);

        $this->assertDatabaseHas('users', ['id' => $gm->id, 'home_branch_id' => null]);
    });

    it('allows only super-admin to update or deactivate a general manager', function (): void {
        $gm = User::factory()->create();
        $gm->businessRoles()->attach(staffRole('general_manager'), [
            'branch_id' => null,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);

        // 1. A general manager trying to update/deactivate another general manager
        $anotherGm = User::factory()->create();
        $anotherGm->businessRoles()->attach(staffRole('general_manager'), [
            'branch_id' => null,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);
        Sanctum::actingAs($anotherGm);

        $this->patchJson("/api/v1/staff/{$gm->id}", [
            'is_active' => false,
        ])->assertStatus(403);

        // 2. A super-admin can update/deactivate a general manager
        $superAdmin = User::factory()->create();
        $superAdmin->businessRoles()->attach(staffRole('super-admin'), [
            'branch_id' => null,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);
        Sanctum::actingAs($superAdmin);

        $this->patchJson("/api/v1/staff/{$gm->id}", [
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.is_active', false);
    });
});
