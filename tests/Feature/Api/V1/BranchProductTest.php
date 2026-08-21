<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\DistributorCategory;
use App\Models\FinancialProduct;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(Database\Seeders\RolesAndPermissionSeeder::class);
});

function productRole(string $code): Role
{
    return Role::query()->firstOrCreate(['code' => $code], ['name' => $code, 'guard_name' => 'web', 'is_active' => true]);
}

function productSignIn(string $roleCode, ?Branch $branch = null): User
{
    $user = User::factory()->create();
    $user->businessRoles()->attach(productRole($roleCode), [
        'branch_id' => $branch?->id,
        'assigned_at' => now(),
        'is_primary' => true,
    ]);
    Sanctum::actingAs($user);

    return $user;
}

function productPayload(array $overrides = []): array
{
    return array_merge([
        'code' => 'VALE-8000',
        'name' => 'Vale Zapatería 8K',
        'description' => 'Vale de 8,000 MXN a 2 quincenas',
        'principal_amount' => '8000.00',
        'number_of_fortnights' => 2,
        'disbursement_method' => 'TRANSFERENCIA',
        'is_active' => true,
    ], $overrides);
}

function productCategory(Branch $branch): DistributorCategory
{
    return DistributorCategory::factory()->create(['branch_id' => $branch->id]);
}

describe('Branch products', function (): void {
    it('lets a general manager create a product for a branch with a category', function (): void {
        $branch = Branch::factory()->create();
        $category = productCategory($branch);
        productSignIn('general_manager');

        $this->postJson("/api/v1/branches/{$branch->id}/products", productPayload(['category_id' => $category->id]))
            ->assertCreated()
            ->assertJsonPath('data.name', 'Vale Zapatería 8K')
            ->assertJsonPath('data.branch_id', $branch->id)
            ->assertJsonPath('data.category_id', $category->id)
            ->assertJsonPath('data.category.name', $category->name);

        $this->assertDatabaseHas('financial_products', [
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'code' => 'VALE-8000',
        ]);
    });

    // El gerente de sucursal solo puede consultar el catálogo de vales
    // (products.view); crearlos/editarlos quedó exclusivamente para el
    // gerente general, incluso dentro de su propia sucursal.
    it('forbids a branch manager from creating a product even in their own branch', function (): void {
        $branch = Branch::factory()->create();
        $category = productCategory($branch);
        productSignIn('branch_manager', $branch);

        $this->postJson("/api/v1/branches/{$branch->id}/products", productPayload(['category_id' => $category->id]))
            ->assertForbidden();
    });

    it('only lists products of the requested branch', function (): void {
        $myBranch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $category = productCategory($myBranch);
        productSignIn('branch_manager', $myBranch);

        FinancialProduct::query()->create([
            'branch_id' => $myBranch->id,
            'category_id' => $category->id,
            'code' => 'VAL-MIO-0001',
            'name' => 'Mi vale',
            'principal_amount' => 5000.00,
            'number_of_fortnights' => 2,
            'disbursement_method' => 'TRANSFERENCIA',
        ]);

        FinancialProduct::query()->create([
            'branch_id' => $otherBranch->id,
            'category_id' => $category->id,
            'code' => 'VAL-OTRO-0001',
            'name' => 'Vale de otra sucursal',
            'principal_amount' => 5000.00,
            'number_of_fortnights' => 2,
            'disbursement_method' => 'TRANSFERENCIA',
        ]);

        $this->getJson("/api/v1/branches/{$myBranch->id}/products")
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.name', 'Mi vale');
    });

    it('forbids a branch manager from creating products in another branch', function (): void {
        $myBranch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        productSignIn('branch_manager', $myBranch);

        $this->postJson("/api/v1/branches/{$otherBranch->id}/products", productPayload())
            ->assertForbidden();
    });

    it('lets a general manager update products of any branch', function (): void {
        $myBranch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $category = productCategory($myBranch);
        productSignIn('general_manager');

        $mine = FinancialProduct::query()->create([
            'branch_id' => $myBranch->id,
            'code' => 'VAL-MIO-0001',
            'name' => 'Mi vale',
            'principal_amount' => 5000.00,
            'number_of_fortnights' => 2,
            'disbursement_method' => 'TRANSFERENCIA',
        ]);

        $theirs = FinancialProduct::query()->create([
            'branch_id' => $otherBranch->id,
            'code' => 'VAL-OTRO-0001',
            'name' => 'Vale de otra sucursal',
            'principal_amount' => 5000.00,
            'number_of_fortnights' => 2,
            'disbursement_method' => 'TRANSFERENCIA',
        ]);

        $this->patchJson("/api/v1/branches/{$myBranch->id}/products/{$mine->id}", [
            'name' => 'Vale actualizado',
            'category_id' => $category->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Vale actualizado')
            ->assertJsonPath('data.category_id', $category->id);

        $this->patchJson("/api/v1/branches/{$otherBranch->id}/products/{$theirs->id}", [
            'name' => 'Vale de otra sucursal actualizado',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Vale de otra sucursal actualizado');
    });

    it('forbids a branch manager from updating a product of their own branch', function (): void {
        $branch = Branch::factory()->create();
        productSignIn('branch_manager', $branch);

        $product = FinancialProduct::query()->create([
            'branch_id' => $branch->id,
            'code' => 'VAL-MIO-0001',
            'name' => 'Mi vale',
            'principal_amount' => 5000.00,
            'number_of_fortnights' => 2,
            'disbursement_method' => 'TRANSFERENCIA',
        ]);

        $this->patchJson("/api/v1/branches/{$branch->id}/products/{$product->id}", [
            'name' => 'No debería',
        ])->assertForbidden();
    });

    it('auto-generates a product code when omitted', function (): void {
        $branch = Branch::factory()->create(['code' => 'SUC-001']);
        $category = productCategory($branch);
        productSignIn('general_manager');

        $payload = productPayload();
        unset($payload['code']);

        $this->postJson("/api/v1/branches/{$branch->id}/products", $payload)
            ->assertCreated()
            ->assertJsonPath('data.code', 'VAL-SUC-001-0001');
    });

    it('lets a general manager create products for any branch', function (): void {
        $branch = Branch::factory()->create();
        $category = productCategory($branch);
        productSignIn('general_manager');

        $this->postJson("/api/v1/branches/{$branch->id}/products", productPayload(['category_id' => $category->id]))
            ->assertCreated()
            ->assertJsonPath('data.branch_id', $branch->id);
    });

    it('rejects an invalid category', function (): void {
        $branch = Branch::factory()->create();
        productSignIn('general_manager');

        $this->postJson("/api/v1/branches/{$branch->id}/products", productPayload(['category_id' => 99999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_id');
    });

    it('inherits the insurance amount from the branch tariff when omitted', function (): void {
        $branch = Branch::factory()->create();
        BranchSetting::query()->create([
            'branch_id' => $branch->id,
            'insurance_rates_json' => [
                ['min_amount' => 0, 'max_amount' => 4999.99, 'insurance_amount' => 50.00],
                ['min_amount' => 5000, 'max_amount' => 9999.99, 'insurance_amount' => 100.00],
            ],
        ]);
        productSignIn('general_manager');

        $payload = productPayload(['principal_amount' => '8000.00']);
        unset($payload['insurance_amount']);

        $this->postJson("/api/v1/branches/{$branch->id}/products", $payload)
            ->assertCreated()
            ->assertJsonPath('data.insurance_amount', '100.00');

        $this->assertDatabaseHas('financial_products', [
            'code' => 'VALE-8000',
            'insurance_amount' => 100.00,
        ]);
    });

    it('keeps the explicit insurance amount when provided', function (): void {
        $branch = Branch::factory()->create();
        BranchSetting::query()->create([
            'branch_id' => $branch->id,
            'insurance_rates_json' => [
                ['min_amount' => 0, 'max_amount' => 9999.99, 'insurance_amount' => 100.00],
            ],
        ]);
        productSignIn('general_manager');

        $this->postJson("/api/v1/branches/{$branch->id}/products", productPayload(['insurance_amount' => '250.00']))
            ->assertCreated()
            ->assertJsonPath('data.insurance_amount', '250.00');
    });

    it('treats the tier max_amount as inclusive — a principal exactly at the boundary still matches', function (): void {
        // Antes se comparaba con `<` (exclusivo): un tramo "5001-8000" no
        // cubría un vale de exactamente $8000, y el seguro se quedaba en $0
        // aunque la sucursal sí tuviera tarifas configuradas para ese monto.
        $branch = Branch::factory()->create();
        BranchSetting::query()->create([
            'branch_id' => $branch->id,
            'insurance_rates_json' => [
                ['min_amount' => 2000, 'max_amount' => 5000, 'insurance_amount' => 300.00],
                ['min_amount' => 5001, 'max_amount' => 8000, 'insurance_amount' => 350.00],
            ],
        ]);
        productSignIn('general_manager');

        $payload = productPayload(['principal_amount' => '8000.00']);
        unset($payload['insurance_amount']);

        $this->postJson("/api/v1/branches/{$branch->id}/products", $payload)
            ->assertCreated()
            ->assertJsonPath('data.insurance_amount', '350.00');
    });

    it('falls back to zero insurance when no tier covers the amount', function (): void {
        $branch = Branch::factory()->create();
        BranchSetting::query()->create([
            'branch_id' => $branch->id,
            'insurance_rates_json' => [
                ['min_amount' => 0, 'max_amount' => 9999.99, 'insurance_amount' => 100.00],
            ],
        ]);
        productSignIn('general_manager');

        $this->postJson("/api/v1/branches/{$branch->id}/products", productPayload(['principal_amount' => '30000.00']))
            ->assertCreated()
            ->assertJsonPath('data.insurance_amount', '0.00');
    });

    it('lets a distributor view products of their own branch but not another branch', function (): void {
        $myBranch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        FinancialProduct::query()->create([
            'branch_id' => $myBranch->id,
            'code' => 'VAL-MIO-0001',
            'name' => 'Mi vale',
            'principal_amount' => 5000.00,
            'number_of_fortnights' => 2,
            'disbursement_method' => 'TRANSFERENCIA',
        ]);
        productSignIn('distributor', $myBranch);

        $this->getJson("/api/v1/branches/{$myBranch->id}/products")
            ->assertOk()
            ->assertJsonCount(1, 'data.data');

        $this->getJson("/api/v1/branches/{$otherBranch->id}/products")
            ->assertForbidden();
    });

    it('forbids a distributor from creating products', function (): void {
        $branch = Branch::factory()->create();
        productSignIn('distributor', $branch);

        $this->postJson("/api/v1/branches/{$branch->id}/products", productPayload())
            ->assertForbidden();
    });

    it('inherits commission, interest and late fee from the branch settings when omitted', function (): void {
        $branch = Branch::factory()->create();
        BranchSetting::query()->create([
            'branch_id' => $branch->id,
            'opening_commission_percentage' => 12.5000,
            'biweekly_interest_percentage' => 4.0000,
            'late_payment_penalty_amount' => 250.00,
        ]);
        productSignIn('general_manager');

        $payload = productPayload();
        unset($payload['company_commission_percentage'], $payload['fortnightly_interest_percentage'], $payload['late_fee_amount']);

        $this->postJson("/api/v1/branches/{$branch->id}/products", $payload)
            ->assertCreated()
            ->assertJsonPath('data.company_commission_percentage', '12.5000')
            ->assertJsonPath('data.fortnightly_interest_percentage', '4.0000')
            ->assertJsonPath('data.late_fee_amount', '250.00');
    });

    it('keeps explicit commission, interest and late fee when provided', function (): void {
        $branch = Branch::factory()->create();
        BranchSetting::query()->create([
            'branch_id' => $branch->id,
            'opening_commission_percentage' => 12.5000,
            'biweekly_interest_percentage' => 4.0000,
            'late_payment_penalty_amount' => 250.00,
        ]);
        productSignIn('general_manager');

        $this->postJson("/api/v1/branches/{$branch->id}/products", productPayload([
            'company_commission_percentage' => '10.0000',
            'fortnightly_interest_percentage' => '5.0000',
            'late_fee_amount' => '300.00',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.company_commission_percentage', '10.0000')
            ->assertJsonPath('data.fortnightly_interest_percentage', '5.0000')
            ->assertJsonPath('data.late_fee_amount', '300.00');
    });

    it('includes global products in the branch catalog marked as global', function (): void {
        $branch = Branch::factory()->create();
        productSignIn('branch_manager', $branch);

        FinancialProduct::query()->create([
            'branch_id' => null,
            'code' => 'VALOR-15000',
            'name' => 'Vale por Valor 15K',
            'principal_amount' => 15000.00,
            'number_of_fortnights' => 8,
            'disbursement_method' => 'TRANSFERENCIA',
        ]);

        $this->getJson("/api/v1/branches/{$branch->id}/products")
            ->assertOk()
            ->assertJsonPath('data.data.0.code', 'VALOR-15000')
            ->assertJsonPath('data.data.0.origin', 'global')
            ->assertJsonPath('data.data.0.branch_id', null);
    });
});
