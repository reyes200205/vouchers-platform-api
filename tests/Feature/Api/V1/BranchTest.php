<?php

declare(strict_types=1);

use App\Models\Branch;
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
        ])->assertOk()->assertJsonPath('data.cutoff_day', 15);

        $this->assertDatabaseHas('branch_settings_logs', [
            'branch_id' => $branch['id'],
            'updated_by_user_id' => $manager->id,
        ]);
    });

    it('creates a branch with no manager, to be assigned later', function (): void {
        $manager = User::factory()->create();
        actingAsBusinessRole($manager, 'general_manager');

        $this->postJson('/api/v1/branches', [
            'name' => 'Sucursal Sin Gerente',
            'address' => 'Calle 1',
            'phone' => '8180000001',
        ])->assertCreated()->assertJsonPath('data.manager', null);
    });

    it('shows a general manager as the branch manager only when it is explicitly their home branch', function (): void {
        Role::query()->firstOrCreate(
            ['name' => 'general_manager', 'guard_name' => 'web'],
            ['code' => 'general_manager']
        );

        $utt = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();

        $gm = User::factory()->create(['home_branch_id' => $utt->id]);
        $gm->businessRoles()->attach(
            Role::where('name', 'general_manager')->first(),
            ['branch_id' => null, 'assigned_at' => now(), 'is_primary' => true]
        );

        Sanctum::actingAs($gm);

        // UTT es su sucursal base explicita: debe aparecer como su "Gerente".
        $this->getJson("/api/v1/branches/{$utt->id}")
            ->assertOk()
            ->assertJsonPath('data.manager.id', $gm->id);

        // La otra sucursal NO es su base -- aunque el gerente general tenga
        // acceso global, no debe aparecer "por defecto" como su gerente.
        $this->getJson("/api/v1/branches/{$otherBranch->id}")
            ->assertOk()
            ->assertJsonPath('data.manager', null);
    });

    it('lets a general manager also be assigned as a branch\'s manager without losing their global reach', function (): void {
        // El gerente general de la sucursal matriz, por ejemplo, también
        // funge como gerente de esa sucursal en particular -- hoy no hay
        // forma de reflejar eso: el gerente general siempre se trata como
        // global únicamente. La forma de asignarlo (el campo "Gerente" al
        // editar una sucursal, que ya incluye a los gerentes generales entre
        // los candidatos -- ver BranchController::availableManagers) le da
        // al usuario un SEGUNDO rol de negocio (branch_manager, con
        // is_primary=false) atado a esa sucursal, sin tocar su rol de
        // gerente general (que se sigue guardando con branch_id NULL). Este
        // test deja constancia de que esa combinación ya funciona de punta
        // a punta y que no se rompe si se vuelve a guardar el mismo valor.
        Role::query()->firstOrCreate(
            ['name' => 'branch_manager', 'guard_name' => 'web'],
            ['code' => 'branch_manager']
        );

        $generalManager = User::factory()->create();
        actingAsBusinessRole($generalManager, 'general_manager');

        $matriz = Branch::factory()->create(['name' => 'Sucursal Matriz']);

        $this->patchJson("/api/v1/branches/{$matriz->id}", [
            'manager_user_id' => $generalManager->id,
        ])->assertOk()->assertJsonPath('data.manager.id', $generalManager->id);

        // Sigue siendo global: puede ver/gestionar cualquier otra sucursal,
        // no solo la matriz.
        $otherBranch = Branch::factory()->create();
        $this->getJson("/api/v1/branches/{$otherBranch->id}")->assertOk();
        $this->patchJson("/api/v1/branches/{$otherBranch->id}", ['name' => 'Otra Sucursal'])->assertOk();

        // Guardar el mismo gerente otra vez (el usuario reabre el formulario
        // y da clic en "Guardar" sin cambiar nada) no debe duplicar el rol
        // ni tronar por una restricción única.
        $this->patchJson("/api/v1/branches/{$matriz->id}", [
            'manager_user_id' => $generalManager->id,
        ])->assertOk()->assertJsonPath('data.manager.id', $generalManager->id);

        $this->assertDatabaseCount('model_has_roles', 2);

        $generalManager->refresh();
        expect($generalManager->isGeneralManager())->toBeTrue()
            ->and($generalManager->hasGlobalBusinessRole())->toBeTrue()
            ->and($generalManager->activeBusinessBranchIds())->toBe([$matriz->id]);
    });

    it('keeps a general manager fully global even when their OWN role row carries a branch_id', function (): void {
        // Bug encontrado en una auditoria externa (confirmado aqui con una
        // prueba real contra MySQL/MariaDB ademas de SQLite, no solo
        // leyendo el codigo): hasGlobalBusinessRole()/isGeneralManager()
        // forzaban el team_id de Spatie a null antes de consultar, así que
        // solo reconocían al gerente general como global si SU PROPIA fila
        // en model_has_roles traía branch_id NULL. Pero nada impide (y de
        // hecho otra prueba de este mismo archivo lo hace, y así quedan
        // asignados los gerentes generales reales que "también" son
        // gerentes de una sucursal en particular) que esa fila SÍ traiga un
        // branch_id -- en ese caso, antes, se le trataba como un rol de
        // sucursal cualquiera y perdía su alcance global por completo: no
        // podía ver ni administrar ninguna otra sucursal.
        Role::query()->firstOrCreate(
            ['name' => 'branch_manager', 'guard_name' => 'web'],
            ['code' => 'branch_manager']
        );

        $generalManager = User::factory()->create();
        $role = Role::query()->firstOrCreate(
            ['name' => 'general_manager', 'guard_name' => 'web'],
            ['code' => 'general_manager']
        );
        $matriz = Branch::factory()->create();
        // A propósito CON branch_id -- el patrón que rompía el alcance
        // global.
        $generalManager->businessRoles()->attach($role, [
            'branch_id' => $matriz->id,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);
        Sanctum::actingAs($generalManager);

        expect($generalManager->isGeneralManager())->toBeTrue()
            ->and($generalManager->hasGlobalBusinessRole())->toBeTrue();

        // Sigue pudiendo ver y administrar OTRA sucursal (distinta a la de
        // su propia fila de rol), sin necesitar ningún vínculo ahí.
        $otherBranch = Branch::factory()->create();
        $this->getJson("/api/v1/branches/{$otherBranch->id}")->assertOk();
        $this->patchJson("/api/v1/branches/{$otherBranch->id}", ['name' => 'Otra Sucursal'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Otra Sucursal');
    it('stops showing a branch manager once their role is revoked from the staff module', function (): void {
        // Reproduce el bug: BranchResource usaba User::role('branch_manager'),
        // el scope nativo de Spatie, que solo mira si existe una fila en
        // model_has_roles e ignora por completo revoked_at. Al cambiarle el rol
        // a alguien desde Staff (que revoca la fila en vez de borrarla), la
        // sucursal seguia mostrando a esa persona como "Gerente".
        $branchManagerRole = Role::query()->firstOrCreate(
            ['name' => 'branch_manager', 'guard_name' => 'web'],
            ['code' => 'branch_manager']
        );
        Role::query()->firstOrCreate(
            ['name' => 'coordinator', 'guard_name' => 'web'],
            ['code' => 'coordinator']
        );

        $branch = Branch::factory()->create();

        $diana = User::factory()->create();
        $diana->businessRoles()->attach($branchManagerRole, [
            'branch_id' => $branch->id,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);

        $generalManager = User::factory()->create();
        actingAsBusinessRole($generalManager, 'general_manager');

        $this->getJson("/api/v1/branches/{$branch->id}")
            ->assertOk()
            ->assertJsonPath('data.manager.id', $diana->id);

        $this->patchJson("/api/v1/staff/{$diana->id}", [
            'is_active' => true,
            'role_code' => 'coordinator',
        ])->assertOk();

        $this->getJson("/api/v1/branches/{$branch->id}")
            ->assertOk()
            ->assertJsonPath('data.manager', null);
    });

    it('allows a coordinator to view but not modify a branch', function (): void {
        $coordinator = User::factory()->create();
        $branch = Branch::factory()->create();

        $role = Role::query()->firstOrCreate(
            ['name' => 'coordinator', 'guard_name' => 'web'],
            ['code' => 'coordinator']
        );
        $coordinator->businessRoles()->attach($role, [
            'branch_id' => $branch->id,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);
        Sanctum::actingAs($coordinator);

        $this->getJson("/api/v1/branches/{$branch->id}")->assertOk();
        $this->patchJson("/api/v1/branches/{$branch->id}", ['name' => 'No permitido'])->assertForbidden();
    });
});
});
