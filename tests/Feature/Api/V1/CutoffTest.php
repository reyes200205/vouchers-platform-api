<?php

declare(strict_types=1);

use App\Enums\VoucherStatus;
use App\Models\Branch;
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
    $user->businessRoles()->attach($role, [
        'branch_id' => $branch->id,
        'assigned_at' => now(),
        'is_primary' => true,
    ]);
    Sanctum::actingAs($user);
}

/**
 * Vale con una quincena programada (fortnightly_payment_amount = 2825.00 sobre
 * total_debt_amount = 22600.00 en 8 quincenas) cuya utilidad para la
 * distribuidora (distributor_profit_amount = 1200.00) reparte 150.00 de
 * comisión por quincena — ver GenerateCutoffService::calculateDistributorCommission().
 * El cliente le paga esto a la distribuidora fuera del sistema; lo único que
 * el corte necesita del vale es su calendario (payment_due_date) y sus montos.
 */
function cutoffVoucher(Branch $branch, Distributor $distributor, string $dueDate, float $lateFee = 300.00): Voucher
{
    return Voucher::factory()->create([
        'branch_id' => $branch->id,
        'distributor_id' => $distributor->id,
        'status' => VoucherStatus::ACTIVO,
        'payment_due_date' => $dueDate,
        'late_fee_amount_snapshot' => $lateFee,
        'distributor_profit_amount' => 1200.00,
        'total_debt_amount' => 22600.00,
        'fortnightly_payment_amount' => 2825.00,
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
        $voucher = cutoffVoucher($branch, $distributor, now()->toDateString());

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
            'commission_amount' => 150.00,
            'late_fee_amount' => 0.00,
            'is_late_payment' => 0,
            'line_total_amount' => 2675.00,
        ]);

        $this->assertDatabaseHas('cutoff_relations', [
            'id' => $relation->id,
            'total_payment' => 2825.00,
            'total_commission' => 150.00,
            'total_late_fees' => 0.00,
            'total_amount_due' => 2675.00,
        ]);

        expect($relation->payment_reference)->toStartWith('REF-');
    });

    it('never applies a late fee at generation time — lateness is only decided later, when the relation is marked VENCIDA', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        // El vale ya está vencido desde antes de que abriera el periodo del
        // corte, pero eso no importa aquí: el corte solo agenda lo que le toca
        // cobrar a la distribuidora, nunca decide si va a llegar tarde.
        $voucher = cutoffVoucher($branch, $distributor, now()->subDays(1)->toDateString(), 300.00);

        $manager = User::factory()->create();
        cutoffSignInBusinessRole($manager, 'branch_manager', $branch);

        $this->postJson("/api/v1/branches/{$branch->id}/cutoffs/generate", [
            'period_start' => now()->subDays(15)->toDateString(),
            'period_end' => now()->toDateString(),
        ])->assertCreated();

        $this->assertDatabaseHas('cutoff_relation_items', [
            'voucher_id' => $voucher->id,
            'late_fee_amount' => 0.00,
            'is_late_payment' => 0,
            'line_total_amount' => 2675.00,
        ]);
    });

    it('carries unpaid relations into the next cutoff', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        cutoffVoucher($branch, $distributor, now()->toDateString());

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
        expect((float) $secondRelation->total_carryover_received)->toBe(2675.00);
        expect((float) $secondRelation->total_amount_due)->toBe(2675.00);

        $this->assertDatabaseHas('cutoff_relations', [
            'id' => $firstRelation->id,
            'status' => 'CERRADA',
        ]);

        $this->assertDatabaseHas('cutoff_relation_items', [
            'cutoff_relation_id' => $secondRelation->id,
            'origin_relation_id' => $firstRelation->id,
            'payment_amount' => 2675.00,
        ]);
    });

    it('skips distributors without vouchers due or unpaid relations', function (): void {
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
        cutoffVoucher($branch, $distributor, now()->toDateString());

        $manager = User::factory()->create();
        cutoffSignInBusinessRole($manager, 'branch_manager', $branch);

        $this->postJson("/api/v1/branches/{$branch->id}/cutoffs/generate", [
            'period_start' => now()->subDays(15)->toDateString(),
            'period_end' => now()->toDateString(),
        ])->assertCreated();

        $cutoff = Cutoff::query()->firstOrFail();
        $originalRelation = CutoffRelation::query()->where('cutoff_id', $cutoff->id)->firstOrFail();

        $gm = User::factory()->create();
        cutoffSignInBusinessRole($gm, 'general_manager', $branch);

        $response = $this->postJson("/api/v1/cutoffs/{$cutoff->id}/reprocess")
            ->assertCreated()
            ->assertJsonPath('data.status', 'EJECUTADO');

        $this->assertDatabaseHas('cutoffs', [
            'id' => $cutoff->id,
            'status' => 'REPROCESADO',
        ]);

        $newCutoffId = $response->json('data.id');
        $newRelation = CutoffRelation::query()
            ->where('cutoff_id', $newCutoffId)
            ->where('distributor_id', $distributor->id)
            ->firstOrFail();

        // previous_relation_id es una FK a cutoff_relations.id, no a cutoffs.id:
        // debe apuntar a la relación original de esta distribuidora, y esa
        // relación original debe quedar cerrada para no contar el saldo doble.
        expect($newRelation->previous_relation_id)->toBe($originalRelation->id);
        $this->assertDatabaseHas('cutoff_relations', [
            'id' => $originalRelation->id,
            'status' => 'CERRADA',
        ]);
    });

    it('marks overdue relations as VENCIDA', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        cutoffVoucher($branch, $distributor, now()->subDays(25)->toDateString());

        $manager = User::factory()->create();
        cutoffSignInBusinessRole($manager, 'branch_manager', $branch);

        $this->postJson("/api/v1/branches/{$branch->id}/cutoffs/generate", [
            'period_start' => now()->subDays(30)->toDateString(),
            'period_end' => now()->subDays(20)->toDateString(),
        ])->assertCreated();

        $relation = CutoffRelation::query()->firstOrFail();
        expect($relation->payment_due_date->isPast())->toBeTrue();

        $count = (new App\Services\Cutoffs\MarkOverdueRelationsService())->execute();

        expect($count)->toBe(1);
        $this->assertDatabaseHas('cutoff_relations', [
            'id' => $relation->id,
            'status' => 'VENCIDA',
        ]);
    });
});
