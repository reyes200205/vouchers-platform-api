<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Cutoff;
use App\Models\CutoffRelation;
use App\Models\Distributor;
use App\Models\FinancialProduct;
use App\Models\Role;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function dashboardSignIn(User $user, string $roleCode = 'general_manager'): void
{
    $role = Role::query()->firstOrCreate(['code' => $roleCode], ['name' => $roleCode]);
    $user->businessRoles()->attach($role, [
        'branch_id' => null,
        'assigned_at' => now(),
        'is_primary' => true,
    ]);
    Sanctum::actingAs($user);
}

describe('Dashboard stats', function (): void {
    it('returns summary and monthly series for a general manager', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'credit_limit' => 100000,
            'available_credit' => 100000,
        ]);

        $customer = Customer::factory()->create();
        $product = FinancialProduct::factory()->create();
        $voucher = Voucher::factory()->create([
            'distributor_id' => $distributor->id,
            'customer_id' => $customer->id,
            'financial_product_id' => $product->id,
            'branch_id' => $branch->id,
            'status' => 'ACTIVO',
            'issued_at' => now()->subMonth(),
        ]);

        CustomerPayment::query()->create([
            'voucher_id' => $voucher->id,
            'customer_id' => $customer->id,
            'distributor_id' => $distributor->id,
            'payment_date' => now(),
            'amount' => 2500.00,
            'payment_method' => 'EFECTIVO',
        ]);

        $cutoff = Cutoff::factory()->create(['branch_id' => $branch->id]);
        CutoffRelation::query()->create([
            'cutoff_id' => $cutoff->id,
            'distributor_id' => $distributor->id,
            'relation_number' => 'REL-1',
            'payment_due_date' => now()->subDays(3),
            'total_amount_due' => 10000.00,
            'status' => 'VENCIDA',
        ]);

        $manager = User::factory()->create();
        dashboardSignIn($manager);

        $response = $this->getJson('/api/v1/stats/dashboard')
            ->assertOk()
            ->assertJsonPath('data.credit_placed', 100000)
            ->assertJsonPath('data.collections_today', 2500)
            ->assertJsonPath('data.active_vouchers', 1)
            ->assertJsonStructure([
                'data' => [
                    'credit_placed',
                    'delinquency_rate',
                    'collections_today',
                    'active_vouchers',
                    'monthly_placement',
                    'monthly_collections',
                ],
            ]);

        $placement = collect($response->json('data.monthly_placement'));
        $this->assertSame(12, $placement->count());
        $this->assertGreaterThan(0, $placement->firstWhere('month', now()->startOfMonth()->subMonth()->format('Y-m'))['amount']);
    });

    it('filters by branch_id', function (): void {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        Distributor::factory()->create(['branch_id' => $branchA->id, 'credit_limit' => 80000, 'available_credit' => 80000]);
        Distributor::factory()->create(['branch_id' => $branchB->id, 'credit_limit' => 30000, 'available_credit' => 30000]);

        $manager = User::factory()->create();
        dashboardSignIn($manager);

        $this->getJson('/api/v1/stats/dashboard?branch_id='.$branchA->id)
            ->assertOk()
            ->assertJsonPath('data.credit_placed', 80000);
    });

    it('forbids a distributor from viewing the dashboard', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);

        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate(['code' => 'distributor'], ['name' => 'distributor']);
        $user->businessRoles()->attach($role, [
            'branch_id' => $branch->id,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/stats/dashboard')->assertForbidden();
    });
});