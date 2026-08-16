<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\VoucherStatus;
use App\Models\Branch;
use App\Models\CustomerPayment;
use App\Models\Distributor;
use App\Models\DistributorCategory;
use App\Models\PointRedemption;
use App\Models\Role;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function pointSignInAsDistributor(User $user, Distributor $distributor): void
{
    $user->update(['person_id' => $distributor->person_id]);
    $role = Role::query()->firstOrCreate(['code' => 'distributor'], ['name' => 'distributor']);
    $user->businessRoles()->attach($role, [
        'branch_id' => $distributor->branch_id,
        'assigned_at' => now(),
        'is_primary' => true,
    ]);
    Sanctum::actingAs($user);
}

function pointSignInBusinessRole(User $user, string $roleCode, Branch $branch): void
{
    $role = Role::query()->firstOrCreate(['code' => $roleCode], ['name' => $roleCode]);
    $user->businessRoles()->attach($role, [
        'branch_id' => $branch->id,
        'assigned_at' => now(),
        'is_primary' => true,
    ]);
    Sanctum::actingAs($user);
}

function pointVoucher(Branch $branch, Distributor $distributor, string $dueDate, string $earlyStart, string $earlyEnd): Voucher
{
    return Voucher::factory()->create([
        'branch_id' => $branch->id,
        'distributor_id' => $distributor->id,
        'status' => VoucherStatus::ACTIVO,
        'payment_due_date' => $dueDate,
        'early_payment_start_date' => $earlyStart,
        'early_payment_end_date' => $earlyEnd,
        'current_balance' => 22600.00,
    ]);
}

describe('Points', function (): void {
    it('grants early payment bonus points and applies late penalties in the cutoff', function (): void {
        $branch = Branch::factory()->create();
        $category = DistributorCategory::factory()->create(['points_per_1200' => 1]);
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'current_points' => 0,
        ]);

        $earlyVoucher = pointVoucher($branch, $distributor, now()->addDays(10)->toDateString(), now()->subDays(10)->toDateString(), now()->subDays(5)->toDateString());
        CustomerPayment::query()->create([
            'voucher_id' => $earlyVoucher->id,
            'customer_id' => $earlyVoucher->customer_id,
            'distributor_id' => $distributor->id,
            'payment_date' => now()->subDays(6),
            'amount' => 1200.00,
            'payment_method' => PaymentMethod::EFECTIVO,
        ]);

        $lateVoucher = pointVoucher($branch, $distributor, now()->subDays(20)->toDateString(), now()->subDays(30)->toDateString(), now()->subDays(25)->toDateString());
        CustomerPayment::query()->create([
            'voucher_id' => $lateVoucher->id,
            'customer_id' => $lateVoucher->customer_id,
            'distributor_id' => $distributor->id,
            'payment_date' => now()->subDays(15),
            'amount' => 1200.00,
            'payment_method' => PaymentMethod::EFECTIVO,
        ]);

        $manager = User::factory()->create();
        pointSignInBusinessRole($manager, 'branch_manager', $branch);

        $this->postJson("/api/v1/branches/{$branch->id}/cutoffs/generate", [
            'period_start' => now()->subDays(30)->toDateString(),
            'period_end' => now()->toDateString(),
        ])->assertCreated();

        $this->assertDatabaseHas('point_movements', [
            'distributor_id' => $distributor->id,
            'voucher_id' => $earlyVoucher->id,
            'transaction_type' => 'GANADO_ANTICIPADO',
            'points' => 1,
        ]);

        $this->assertDatabaseHas('point_movements', [
            'distributor_id' => $distributor->id,
            'voucher_id' => $lateVoucher->id,
            'transaction_type' => 'PENALIZACION_ATRASO',
            'points' => -1,
        ]);

        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'current_points' => 0.00,
        ]);
    });

    it('lets a distributor request a point redemption and the manager approves it', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'current_points' => 500,
        ]);

        $distributorUser = User::factory()->create();
        pointSignInAsDistributor($distributorUser, $distributor);

        $this->postJson("/api/v1/distributors/{$distributor->id}/points/redeem", [
            'points' => '200.00',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'PENDIENTE')
            ->assertJsonPath('data.amount_mxn', '400.00');

        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'current_points' => 500.00,
        ]);

        $manager = User::factory()->create();
        pointSignInBusinessRole($manager, 'general_manager', $branch);

        $redemption = PointRedemption::query()->firstOrFail();

        $this->postJson("/api/v1/point-redemptions/{$redemption->id}/decision", [
            'decision' => 'APROBADO',
        ])->assertOk()
            ->assertJsonPath('data.status', 'APROBADO')
            ->assertJsonPath('data.decided_by_user_id', $manager->id);

        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'current_points' => 300.00,
        ]);

        $this->assertDatabaseHas('point_movements', [
            'distributor_id' => $distributor->id,
            'transaction_type' => 'CANJE',
            'points' => -200.00,
        ]);
    });

    it('rejects a redemption that exceeds the available points', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'current_points' => 100,
        ]);

        $distributorUser = User::factory()->create();
        pointSignInAsDistributor($distributorUser, $distributor);

        $this->postJson("/api/v1/distributors/{$distributor->id}/points/redeem", [
            'points' => '500.00',
        ])->assertStatus(422);
    });

    it('rejects a second pending redemption', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'current_points' => 500,
        ]);

        $distributorUser = User::factory()->create();
        pointSignInAsDistributor($distributorUser, $distributor);

        $this->postJson("/api/v1/distributors/{$distributor->id}/points/redeem", ['points' => '100.00'])->assertCreated();
        $this->postJson("/api/v1/distributors/{$distributor->id}/points/redeem", ['points' => '100.00'])->assertStatus(422);
    });

    it('lets the manager change the distributor category', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $newCategory = DistributorCategory::factory()->create(['points_per_1200' => 2]);

        $manager = User::factory()->create();
        pointSignInBusinessRole($manager, 'branch_manager', $branch);

        $this->patchJson("/api/v1/distributors/{$distributor->id}/category", [
            'category_id' => $newCategory->id,
        ])->assertOk()
            ->assertJsonPath('data.category_id', $newCategory->id);

        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'category_id' => $newCategory->id,
        ]);
    });

    it('forbids the distributor from deciding a redemption', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'current_points' => 100,
        ]);
        $redemption = PointRedemption::factory()->create([
            'distributor_id' => $distributor->id,
            'branch_id' => $branch->id,
            'points' => 50,
            'amount_mxn' => 100,
        ]);

        $distributorUser = User::factory()->create();
        pointSignInAsDistributor($distributorUser, $distributor);

        $this->postJson("/api/v1/point-redemptions/{$redemption->id}/decision", [
            'decision' => 'APROBADO',
        ])->assertForbidden();
    });
});