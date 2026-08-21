<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\BranchSettingsLog;
use App\Models\DistributorCategory;
use App\Models\FinancialProduct;
use App\Models\PointSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function loginWithBusinessRole(User $user, string $roleCode, ?Branch $branch = null): void
{
    $role = Role::query()->firstOrCreate(['code' => $roleCode], ['name' => $roleCode]);
    $user->businessRoles()->attach($role, [
        'branch_id' => $branch?->id,
        'assigned_at' => now(),
        'is_primary' => true,
    ]);
    Sanctum::actingAs($user);
}

function categoryPayload(int $branchId): array
{
    return [
        'branch_id' => $branchId,
        'code' => 'BRONCE',
        'name' => 'Bronce',
        'commission_percentage' => '10.0000',
        'points_per_1200' => 3,
        'late_penalty_percentage' => '20.0000',
        'is_active' => true,
    ];
}

describe('Branch settings (config de vales y puntos por sucursal)', function (): void {
    it('allows a branch manager to update the voucher and points configuration of their branch', function (): void {
        $branch = Branch::factory()->create();
        $manager = User::factory()->create();
        loginWithBusinessRole($manager, 'branch_manager', $branch);

        $this->patchJson("/api/v1/branches/{$branch->id}/settings", [
            'voucher_amount_step' => 500,
            'pre_vale_max_percentage' => '50.00',
            'pre_vale_tolerance_amount' => '500.00',
            'point_value_mxn' => '6.00',
        ])->assertOk()
            ->assertJsonPath('data.voucher_amount_step', 500)
            ->assertJsonPath('data.pre_vale_max_percentage', '50.00')
            ->assertJsonPath('data.pre_vale_tolerance_amount', '500.00')
            ->assertJsonPath('data.point_value_mxn', '6.00');

        $this->assertDatabaseHas('branch_settings', [
            'branch_id' => $branch->id,
            'voucher_amount_step' => 500,
            'pre_vale_max_percentage' => 50.00,
            'pre_vale_tolerance_amount' => 500.00,
            'point_value_mxn' => 6.00,
        ]);

        $this->assertDatabaseHas('branch_settings_logs', [
            'branch_id' => $branch->id,
            'updated_by_user_id' => $manager->id,
            'event_type' => 'SUCURSAL',
        ]);
    });

    it('rejects invalid voucher amount steps', function (): void {
        $branch = Branch::factory()->create();
        $manager = User::factory()->create();
        loginWithBusinessRole($manager, 'branch_manager', $branch);

        $this->patchJson("/api/v1/branches/{$branch->id}/settings", [
            'voucher_amount_step' => 250,
        ])->assertUnprocessable();
    });

    it('lets a branch manager configure insurance tariff tiers', function (): void {
        $branch = Branch::factory()->create();
        $manager = User::factory()->create();
        loginWithBusinessRole($manager, 'branch_manager', $branch);

        $this->patchJson("/api/v1/branches/{$branch->id}/settings", [
            'insurance_rates' => [
                ['min_amount' => '0.00', 'max_amount' => '4999.99', 'insurance_amount' => '50.00'],
                ['min_amount' => '5000.00', 'max_amount' => '9999.99', 'insurance_amount' => '100.00'],
                ['min_amount' => '10000.00', 'max_amount' => '99999999.00', 'insurance_amount' => '200.00'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.insurance_rates.1.insurance_amount', '100.00');

        $this->assertDatabaseHas('branch_settings', [
            'branch_id' => $branch->id,
        ]);
        $this->assertDatabaseHas('branch_settings_logs', [
            'branch_id' => $branch->id,
            'event_type' => 'SUCURSAL',
        ]);
    });

    it('rejects overlapping or malformed insurance tariff tiers', function (): void {
        $branch = Branch::factory()->create();
        $manager = User::factory()->create();
        loginWithBusinessRole($manager, 'branch_manager', $branch);

        $this->patchJson("/api/v1/branches/{$branch->id}/settings", [
            'insurance_rates' => [
                ['min_amount' => '0.00', 'max_amount' => '10000.00', 'insurance_amount' => '50.00'],
                ['min_amount' => '5000.00', 'max_amount' => '15000.00', 'insurance_amount' => '100.00'],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('insurance_rates.1');

        $this->patchJson("/api/v1/branches/{$branch->id}/settings", [
            'insurance_rates' => [
                ['min_amount' => '0.00', 'max_amount' => '10000.00'],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('insurance_rates.0.insurance_amount');

        $this->patchJson("/api/v1/branches/{$branch->id}/settings", [
            'insurance_rates' => [
                ['min_amount' => '5000.00', 'max_amount' => '1000.00', 'insurance_amount' => '50.00'],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('insurance_rates.0');
    });

    it('forbids a branch manager from editing settings of another branch', function (): void {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $manager = User::factory()->create();
        loginWithBusinessRole($manager, 'branch_manager', $branchA);

        $this->patchJson("/api/v1/branches/{$branchB->id}/settings", [
            'pre_vale_max_percentage' => '60.00',
        ])->assertForbidden();
    });

    it('uses sensible defaults when settings are first created', function (): void {
        $branch = Branch::factory()->create();
        $manager = User::factory()->create();
        loginWithBusinessRole($manager, 'branch_manager', $branch);

        $this->getJson("/api/v1/branches/{$branch->id}/settings")
            ->assertOk()
            ->assertJsonPath('data.voucher_amount_step', 100)
            ->assertJsonPath('data.pre_vale_max_percentage', '50.00')
            ->assertJsonPath('data.pre_vale_tolerance_amount', '500.00');
    });
});

describe('Distributor categories', function (): void {
    it('allows a general manager to create and update a category', function (): void {
        $manager = User::factory()->create();
        loginWithBusinessRole($manager, 'general_manager');
        $branch = Branch::factory()->create();

        $this->postJson('/api/v1/distributor-categories', categoryPayload($branch->id))
            ->assertCreated()
            ->assertJsonPath('data.code', 'BRONCE')
            ->assertJsonPath('data.commission_percentage', '10.0000');

        $category = DistributorCategory::query()->firstOrFail();

        $this->patchJson("/api/v1/distributor-categories/{$category->id}", [
            'commission_percentage' => '12.0000',
            'points_per_1200' => 5,
        ])->assertOk()
            ->assertJsonPath('data.commission_percentage', '12.0000')
            ->assertJsonPath('data.points_per_1200', 5);
    });

    it('forbids a coordinator from managing categories but allows viewing', function (): void {
        $coordinator = User::factory()->create();
        loginWithBusinessRole($coordinator, 'coordinator');
        $branch = Branch::factory()->create();
        $category = DistributorCategory::query()->create(categoryPayload($branch->id));

        $this->getJson('/api/v1/distributor-categories')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $category->id);

        $this->postJson('/api/v1/distributor-categories', array_merge(categoryPayload($branch->id), [
            'code' => 'PLATA',
            'name' => 'Plata',
        ]))->assertForbidden();
    });

    it('lets a branch manager create a category for their own branch via /branches/{branch}/categories', function (): void {
        // branch_id sale del segmento de la URL, no del cuerpo — antes
        // StoreDistributorCategoryRequest lo exigía en el body y el
        // controlador lo fusionaba DESPUÉS de que ya se había validado, así
        // que esto siempre fallaba con "El campo sucursal es obligatorio".
        $branch = Branch::factory()->create();
        $manager = User::factory()->create();
        loginWithBusinessRole($manager, 'branch_manager', $branch);

        $payload = categoryPayload($branch->id);
        unset($payload['branch_id']);

        $this->postJson("/api/v1/branches/{$branch->id}/categories", $payload)
            ->assertCreated()
            ->assertJsonPath('data.branch_id', $branch->id)
            ->assertJsonPath('data.code', 'BRONCE');

        $this->assertDatabaseHas('distributor_categories', [
            'branch_id' => $branch->id,
            'code' => 'BRONCE',
        ]);
    });

    it('forbids a branch manager from creating a category for a different branch via /branches/{branch}/categories', function (): void {
        $ownBranch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $manager = User::factory()->create();
        loginWithBusinessRole($manager, 'branch_manager', $ownBranch);

        $payload = categoryPayload($otherBranch->id);
        unset($payload['branch_id']);

        $this->postJson("/api/v1/branches/{$otherBranch->id}/categories", $payload)
            ->assertForbidden();
    });

    it('forbids a branch manager from managing categories through the global catalog endpoint', function (): void {
        // Un branch_manager sí puede crear categorías de SU sucursal, pero vía
        // /branches/{branch}/categories (ver BranchManager\CategoryController).
        // El endpoint global /distributor-categories es exclusivo del gerente
        // general, para que un gerente de sucursal no pueda crear/editar
        // categorías de OTRAS sucursales pasando un branch_id arbitrario.
        $branch = Branch::factory()->create();
        $manager = User::factory()->create();
        loginWithBusinessRole($manager, 'branch_manager', $branch);

        $this->postJson('/api/v1/distributor-categories', categoryPayload($branch->id))
            ->assertForbidden();

        $this->assertDatabaseMissing('distributor_categories', ['code' => 'BRONCE']);
    });

    it('lets a general manager move a category to another branch', function (): void {
        $manager = User::factory()->create();
        loginWithBusinessRole($manager, 'general_manager');
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $category = DistributorCategory::query()->create(categoryPayload($branchA->id));

        $this->patchJson("/api/v1/distributor-categories/{$category->id}", [
            'branch_id' => $branchB->id,
        ])->assertOk()
            ->assertJsonPath('data.branch_id', $branchB->id);

        $this->assertDatabaseHas('distributor_categories', [
            'id' => $category->id,
            'branch_id' => $branchB->id,
        ]);
    });

    it('rejects moving a category when the target branch already has the same code or name', function (): void {
        $manager = User::factory()->create();
        loginWithBusinessRole($manager, 'general_manager');
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $category = DistributorCategory::query()->create(categoryPayload($branchA->id));
        DistributorCategory::query()->create(categoryPayload($branchB->id));

        $this->patchJson("/api/v1/distributor-categories/{$category->id}", [
            'branch_id' => $branchB->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('branch_id');
    });
});

describe('Point settings (globales)', function (): void {
    it('allows a general manager to update the global points configuration', function (): void {
        $manager = User::factory()->create();
        loginWithBusinessRole($manager, 'general_manager');

        $this->patchJson('/api/v1/point-settings', [
            'point_divisor_factor' => 1500,
            'point_multiplier' => 4,
            'late_penalty_percentage' => '25.0000',
        ])->assertOk()
            ->assertJsonPath('data.point_divisor_factor', 1500)
            ->assertJsonPath('data.point_multiplier', 4)
            ->assertJsonPath('data.late_penalty_percentage', '25.0000');

        $this->assertDatabaseHas('point_settings', [
            'point_divisor_factor' => 1500,
            'point_multiplier' => 4,
            'late_penalty_percentage' => 25.0000,
            'updated_by_user_id' => $manager->id,
        ]);
    });

    it('forbids a branch manager from updating point settings', function (): void {
        $branchManager = User::factory()->create();
        loginWithBusinessRole($branchManager, 'branch_manager');
        PointSetting::query()->create(['point_divisor_factor' => 1200]);

        $this->patchJson('/api/v1/point-settings', ['point_multiplier' => 9])->assertForbidden();

        $this->assertDatabaseHas('point_settings', ['point_multiplier' => 3]);
    });

    it('forbids a distributor from viewing point settings', function (): void {
        $branch = Branch::factory()->create();
        $distributor = User::factory()->create();
        loginWithBusinessRole($distributor, 'distributor', $branch);

        $this->getJson('/api/v1/point-settings')->assertForbidden();
    });
});

describe('Financial products (catálogo)', function (): void {
    it('forbids a branch manager from creating a product in the global catalog', function (): void {
        // El catálogo global (financial-products sin sucursal) es exclusivo del
        // gerente general — un gerente de sucursal debe crear sus productos vía
        // /branches/{branch}/products (ver BranchProductTest.php), que sí queda
        // scoped a su propia sucursal.
        $branch = Branch::factory()->create();
        $manager = User::factory()->create();
        loginWithBusinessRole($manager, 'branch_manager', $branch);

        $this->postJson('/api/v1/financial-products', [
            'code' => 'QUINCENA-8',
            'name' => 'Plan quincenal 8',
            'principal_amount' => '8000.00',
            'number_of_fortnights' => 8,
            'company_commission_percentage' => '5.0000',
            'insurance_amount' => '100.00',
            'fortnightly_interest_percentage' => '2.5000',
            'late_fee_amount' => '50.00',
            'disbursement_method' => 'TRANSFERENCIA',
            'is_active' => true,
        ])->assertForbidden();

        $this->assertDatabaseMissing('financial_products', ['code' => 'QUINCENA-8']);
    });

    it('allows a general manager to create a product in the global catalog', function (): void {
        $manager = User::factory()->create();
        loginWithBusinessRole($manager, 'general_manager');

        $this->postJson('/api/v1/financial-products', [
            'code' => 'QUINCENA-8',
            'name' => 'Plan quincenal 8',
            'principal_amount' => '8000.00',
            'number_of_fortnights' => 8,
            'company_commission_percentage' => '5.0000',
            'insurance_amount' => '100.00',
            'fortnightly_interest_percentage' => '2.5000',
            'late_fee_amount' => '50.00',
            'disbursement_method' => 'TRANSFERENCIA',
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.code', 'QUINCENA-8')
            ->assertJsonPath('data.branch_id', null);

        $this->assertDatabaseHas('financial_products', ['code' => 'QUINCENA-8', 'branch_id' => null]);
    });

    it('allows a branch manager to view the catalog', function (): void {
        $branch = Branch::factory()->create();
        $manager = User::factory()->create();
        loginWithBusinessRole($manager, 'branch_manager', $branch);
        $product = FinancialProduct::query()->create([
            'code' => 'QUINCENA-12',
            'name' => 'Plan quincenal 12',
            'principal_amount' => '5000.00',
            'number_of_fortnights' => 12,
            'company_commission_percentage' => '5.0000',
            'insurance_amount' => '100.00',
            'fortnightly_interest_percentage' => '2.5000',
            'late_fee_amount' => '50.00',
            'disbursement_method' => 'TRANSFERENCIA',
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/financial-products')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $product->id);
    });
});

describe('Branch settings audit log', function (): void {
    it('records before and after values on every update', function (): void {
        $branch = Branch::factory()->create();
        $manager = User::factory()->create();
        loginWithBusinessRole($manager, 'branch_manager', $branch);

        $this->patchJson("/api/v1/branches/{$branch->id}/settings", [
            'point_value_mxn' => '8.00',
        ])->assertOk();

        $log = BranchSettingsLog::query()->firstOrFail();

        expect($log->before_changes_json)->toBe(['point_value_mxn' => '2.00'])
            ->and($log->after_changes_json)->toBe(['point_value_mxn' => '8.00']);
    });
});
