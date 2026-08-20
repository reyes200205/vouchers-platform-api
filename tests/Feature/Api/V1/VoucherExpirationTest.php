<?php

declare(strict_types=1);

use App\Enums\CustomerDistributorRelationshipStatus;
use App\Enums\CustomerStatus;
use App\Enums\VoucherStatus;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Customer;
use App\Models\CustomerDistributor;
use App\Models\Distributor;
use App\Models\DistributorCategory;
use App\Models\FinancialProduct;
use App\Models\Role;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->branch = Branch::factory()->create();
    $this->setting = BranchSetting::query()->firstOrCreate(['branch_id' => $this->branch->id]);

    $this->category = DistributorCategory::factory()->create(['commission_percentage' => 8.0000]);
    $this->product = FinancialProduct::factory()->create();

    $this->distributor = Distributor::factory()->create([
        'branch_id' => $this->branch->id,
        'category_id' => $this->category->id,
        'credit_limit' => 30000,
        'available_credit' => 30000,
    ]);

    $this->customer = Customer::factory()->create([
        'branch_id' => $this->branch->id,
        'status' => CustomerStatus::ACTIVO,
        'verified_at' => now(),
    ]);

    CustomerDistributor::query()->create([
        'distributor_id' => $this->distributor->id,
        'customer_id' => $this->customer->id,
        'relationship_status' => CustomerDistributorRelationshipStatus::ACTIVA,
        'prevale_approved' => false,
        'blocked_due_to_relationship' => false,
        'linked_at' => now(),
    ]);
});

function signInRole(User $user, string $roleCode, Branch $branch): void
{
    $role = Role::query()->firstOrCreate(['code' => $roleCode], ['name' => $roleCode]);
    $user->businessRoles()->attach($role, [
        'branch_id' => $branch->id,
        'assigned_at' => now(),
        'is_primary' => true,
    ]);
    Sanctum::actingAs($user);
}

describe('Voucher Expiration Settings', function (): void {
    it('can save and update voucher_expiration_days via API', function (): void {
        $manager = User::factory()->create();
        signInRole($manager, 'branch_manager', $this->branch);

        $this->patchJson("/api/v1/branches/{$this->branch->id}/settings", [
            'voucher_expiration_days' => 5,
        ])->assertOk()
            ->assertJsonPath('data.voucher_expiration_days', 5);

        $this->assertDatabaseHas('branch_settings', [
            'branch_id' => $this->branch->id,
            'voucher_expiration_days' => 5,
        ]);
    });

    it('validates voucher_expiration_days value', function (): void {
        $manager = User::factory()->create();
        signInRole($manager, 'branch_manager', $this->branch);

        $this->patchJson("/api/v1/branches/{$this->branch->id}/settings", [
            'voucher_expiration_days' => -1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('voucher_expiration_days');
    });
});

describe('Voucher Expiration on Disbursement', function (): void {
    it('allows disbursement within the expiration window', function (): void {
        $this->setting->update(['voucher_expiration_days' => 5]);

        $voucher = Voucher::factory()->create([
            'distributor_id' => $this->distributor->id,
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'financial_product_id' => $this->product->id,
            'status' => VoucherStatus::APROBADO,
            'issued_at' => now()->subDays(4), // 4 days ago
        ]);

        $cashier = User::factory()->create();
        signInRole($cashier, 'cashier', $this->branch);

        $this->postJson("/api/v1/vouchers/{$voucher->id}/disburse", [
            'transfer_reference' => 'SPEI-12345',
            'authorized_number' => 'AUT-9999',
        ])->assertOk()
            ->assertJsonPath('data.status', 'ACTIVO');
    });

    it('blocks disbursement when the voucher has expired', function (): void {
        $this->setting->update(['voucher_expiration_days' => 5]);

        $voucher = Voucher::factory()->create([
            'distributor_id' => $this->distributor->id,
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'financial_product_id' => $this->product->id,
            'status' => VoucherStatus::APROBADO,
            'issued_at' => now()->subDays(6), // 6 days ago (expired)
        ]);

        $cashier = User::factory()->create();
        signInRole($cashier, 'cashier', $this->branch);

        $this->postJson("/api/v1/vouchers/{$voucher->id}/disburse", [
            'transfer_reference' => 'SPEI-12345',
            'authorized_number' => 'AUT-9999',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'El vale ha vencido y no puede ser dispersado.');
    });
});

describe('Auto-Cancel Expired Vouchers Command', function (): void {
    it('cancels expired vouchers and returns credit to distributor', function (): void {
        $this->setting->update(['voucher_expiration_days' => 5]);

        $voucher = Voucher::factory()->create([
            'distributor_id' => $this->distributor->id,
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'financial_product_id' => $this->product->id,
            'status' => VoucherStatus::APROBADO,
            'issued_at' => now()->subDays(6), // expired
            'total_debt_amount' => 5000.00,
        ]);

        // Mock that credit was decremented beforehand
        $this->distributor->update(['available_credit' => 25000.00]);

        $this->artisan('vouchers:cancel-expired')
            ->assertExitCode(0);

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'status' => 'CANCELADO',
            'is_canceled' => true,
        ]);

        // Credit should be incremented back to 30000.00
        $this->assertDatabaseHas('distributors', [
            'id' => $this->distributor->id,
            'available_credit' => 30000.00,
        ]);
    });

    it('does not cancel vouchers that have not expired yet', function (): void {
        $this->setting->update(['voucher_expiration_days' => 5]);

        $voucher = Voucher::factory()->create([
            'distributor_id' => $this->distributor->id,
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'financial_product_id' => $this->product->id,
            'status' => VoucherStatus::APROBADO,
            'issued_at' => now()->subDays(2), // not expired
            'total_debt_amount' => 5000.00,
        ]);

        $this->distributor->update(['available_credit' => 25000.00]);

        $this->artisan('vouchers:cancel-expired')
            ->assertExitCode(0);

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'status' => 'APROBADO',
            'is_canceled' => false,
        ]);

        $this->assertDatabaseHas('distributors', [
            'id' => $this->distributor->id,
            'available_credit' => 25000.00,
        ]);
    });
});
