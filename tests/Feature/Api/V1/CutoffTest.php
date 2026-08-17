<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\VoucherStatus;
use App\Models\Branch;
use App\Models\CustomerPayment;
use App\Models\Cutoff;
use App\Models\CutoffRelation;
use App\Models\Distributor;
use App\Models\Role;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function cutoffSignInBusinessRole(User $user, string $roleCode, Branch $branch): void
{
    $role = Role::query()->firstOrCreate(['code' => $roleCode], ['name' => $roleCode]);
    $user->update(['role_id' => $role->id, 'branch_id' => $branch->id]);
    Sanctum::actingAs($user);
}

function cutoffVoucher(Branch $branch, Distributor $distributor, string $dueDate, float $lateFee = 300.00): Voucher
{
    return Voucher::factory()->create([
        'branch_id' => $branch->id,
        'distributor_id' => $distributor->id,
        'status' => VoucherStatus::ACTIVO,
        'payment_due_date' => $dueDate,
        'late_fee_amount_snapshot' => $lateFee,
        'distributor_profit_percentage_snapshot' => 8.0000,
        'total_fortnights' => 8,
        'payments_made' => 0,
        'current_balance' => 22600.00,
    ]);
}

describe('Cutoffs', function (): void {
    it('generates a cutoff with relations and commission calculations', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'credit_limit' => 20000,
            'available_credit' => 20000,
            'current_points' => 0,
        ]);
        $voucher = cutoffVoucher($branch, $distributor, now()->addDays(30)->toDateString());

        CustomerPayment::query()->create([
            'voucher_id' => $voucher->id,
            'customer_id' => $voucher->customer_id,
            'distributor_id' => $distributor->id,
            'payment_date' => now()->subDays(5),
            'amount' => 2825.00,
            'payment_method' => PaymentMethod::EFECTIVO,
            'is_partial' => true,
        ]);

        $manager = User::factory()->create();
        cutoffSignInBusinessRole($manager, 'branch_manager', $branch);

        $this->postJson("/api/v1/branches/{$branch->id}/cutoffs/generate", [
            'period_start' => now()->subDays(15)->toDateString(),
            'period_end' => now()->toDateString(),
        ])->assertCreated()
            ->assertJsonPath('data.status', 'EJECUTADO');

        $this->assertDatabaseHas('cutoffs', [
            'branch_id' => $branch->id,
            'status' => 'EJECUTADO',
        ]);

        $cutoff = Cutoff::query()->firstOrFail();
        $this->assertDatabaseHas('cutoff_relations', [
            'cutoff_id' => $cutoff->id,
            'distributor_id' => $distributor->id,
            'status' => 'GENERADA',
        ]);

        $relation = CutoffRelation::query()->firstOrFail();
        $this->assertDatabaseHas('cutoff_relation_items', [
            'cutoff_relation_id' => $relation->id,
            'voucher_id' => $voucher->id,
            'payment_amount' => 2825.00,
            'commission_amount' => 226.00,
            'late_fee_amount' => 0.00,
            'line_total_amount' => 2599.00,
        ]);

        $this->assertDatabaseHas('cutoff_relations', [
            'id' => $relation->id,
            'total_payment' => 2825.00,
            'total_commission' => 226.00,
            'total_late_fees' => 0.00,
            'total_amount_due' => 2599.00,
        ]);

        expect($relation->payment_reference)->toStartWith('REF-');
    });

    it('applies late fees when the customer pays after the due date', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $voucher = cutoffVoucher($branch, $distributor, now()->subDays(10)->toDateString(), 300.00);

        CustomerPayment::query()->create([
            'voucher_id' => $voucher->id,
            'customer_id' => $voucher->customer_id,
            'distributor_id' => $distributor->id,
            'payment_date' => now()->subDays(2),
            'amount' => 2825.00,
            'payment_method' => PaymentMethod::EFECTIVO,
            'is_partial' => true,
        ]);

        $manager = User::factory()->create();
        cutoffSignInBusinessRole($manager, 'branch_manager', $branch);

        $this->postJson("/api/v1/branches/{$branch->id}/cutoffs/generate", [
            'period_start' => now()->subDays(15)->toDateString(),
            'period_end' => now()->toDateString(),
        ])->assertCreated();

        $this->assertDatabaseHas('cutoff_relation_items', [
            'voucher_id' => $voucher->id,
            'late_fee_amount' => 300.00,
            'is_late_payment' => 1,
            'line_total_amount' => 2899.00,
        ]);
    });

    it('carries unpaid relations into the next cutoff', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $voucher = cutoffVoucher($branch, $distributor, now()->addDays(30)->toDateString());

        CustomerPayment::query()->create([
            'voucher_id' => $voucher->id,
            'customer_id' => $voucher->customer_id,
            'distributor_id' => $distributor->id,
            'payment_date' => now()->subDays(5),
            'amount' => 2825.00,
            'payment_method' => PaymentMethod::EFECTIVO,
            'is_partial' => true,
        ]);

        $manager = User::factory()->create();
        cutoffSignInBusinessRole($manager, 'branch_manager', $branch);

        $this->postJson("/api/v1/branches/{$branch->id}/cutoffs/generate", [
            'period_start' => now()->subDays(15)->toDateString(),
            'period_end' => now()->toDateString(),
        ])->assertCreated();

        $firstRelation = CutoffRelation::query()->firstOrFail();
        $this->postJson("/api/v1/branches/{$branch->id}/cutoffs/generate", [
            'period_start' => now()->addDay()->toDateString(),
            'period_end' => now()->addDays(15)->toDateString(),
        ])->assertCreated();

        $secondRelation = CutoffRelation::query()->where('id', '!=', $firstRelation->id)->firstOrFail();

        expect($secondRelation->previous_relation_id)->toBe($firstRelation->id);
        expect((float) $secondRelation->total_carryover_received)->toBe(2599.00);
        expect((float) $secondRelation->total_amount_due)->toBe(2599.00);

        $this->assertDatabaseHas('cutoff_relations', [
            'id' => $firstRelation->id,
            'status' => 'CERRADA',
        ]);

        $this->assertDatabaseHas('cutoff_relation_items', [
            'cutoff_relation_id' => $secondRelation->id,
            'origin_relation_id' => $firstRelation->id,
            'payment_amount' => 2599.00,
        ]);
    });

    it('skips distributors without payments or unpaid relations', function (): void {
        $branch = Branch::factory()->create();
        Distributor::factory()->create(['branch_id' => $branch->id]);

        $manager = User::factory()->create();
        cutoffSignInBusinessRole($manager, 'branch_manager', $branch);

        $this->postJson("/api/v1/branches/{$branch->id}/cutoffs/generate", [
            'period_start' => now()->subDays(15)->toDateString(),
            'period_end' => now()->toDateString(),
        ])->assertCreated();

        $this->assertDatabaseCount('cutoff_relations', 0);
    });

    it('reprocesses a cutoff creating a new executed one', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $voucher = cutoffVoucher($branch, $distributor, now()->addDays(30)->toDateString());

        CustomerPayment::query()->create([
            'voucher_id' => $voucher->id,
            'customer_id' => $voucher->customer_id,
            'distributor_id' => $distributor->id,
            'payment_date' => now()->subDays(5),
            'amount' => 2825.00,
            'payment_method' => PaymentMethod::EFECTIVO,
            'is_partial' => true,
        ]);

        $manager = User::factory()->create();
        cutoffSignInBusinessRole($manager, 'branch_manager', $branch);

        $this->postJson("/api/v1/branches/{$branch->id}/cutoffs/generate", [
            'period_start' => now()->subDays(15)->toDateString(),
            'period_end' => now()->toDateString(),
        ])->assertCreated();

        $cutoff = Cutoff::query()->firstOrFail();

        $gm = User::factory()->create();
        cutoffSignInBusinessRole($gm, 'general_manager', $branch);

        $this->postJson("/api/v1/cutoffs/{$cutoff->id}/reprocess")
            ->assertCreated()
            ->assertJsonPath('data.status', 'EJECUTADO');

        $this->assertDatabaseHas('cutoffs', [
            'id' => $cutoff->id,
            'status' => 'REPROCESADO',
        ]);
    });

    it('marks overdue relations as VENCIDA', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $voucher = cutoffVoucher($branch, $distributor, now()->subDays(5)->toDateString());

        CustomerPayment::query()->create([
            'voucher_id' => $voucher->id,
            'customer_id' => $voucher->customer_id,
            'distributor_id' => $distributor->id,
            'payment_date' => now()->subDays(25),
            'amount' => 2825.00,
            'payment_method' => PaymentMethod::EFECTIVO,
            'is_partial' => true,
        ]);

        $manager = User::factory()->create();
        cutoffSignInBusinessRole($manager, 'branch_manager', $branch);

        $this->postJson("/api/v1/branches/{$branch->id}/cutoffs/generate", [
            'period_start' => now()->subDays(30)->toDateString(),
            'period_end' => now()->subDays(20)->toDateString(),
        ])->assertCreated();

        $relation = CutoffRelation::query()->firstOrFail();
        expect($relation->payment_due_date->isPast())->toBeTrue();

        $count = (new \App\Services\Cutoffs\MarkOverdueRelationsService())->execute();

        expect($count)->toBe(1);
        $this->assertDatabaseHas('cutoff_relations', [
            'id' => $relation->id,
            'status' => 'VENCIDA',
        ]);
    });
});