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

    it('reprocesses a cutoff in place, adding relations for distributors that did not have one yet, without duplicating it', function (): void {
        $branch = Branch::factory()->create();
        $distributorA = Distributor::factory()->create(['branch_id' => $branch->id]);
        cutoffVoucher($branch, $distributorA, now()->toDateString());

        $manager = User::factory()->create();
        cutoffSignInBusinessRole($manager, 'branch_manager', $branch);

        $this->postJson("/api/v1/branches/{$branch->id}/cutoffs/generate", [
            'period_start' => now()->subDays(15)->toDateString(),
            'period_end' => now()->toDateString(),
        ])->assertCreated();

        $cutoff = Cutoff::query()->firstOrFail();
        $this->assertDatabaseCount('cutoff_relations', 1);

        // Una distribuidora nueva (con vale en el mismo periodo) se da de alta
        // DESPUÉS de generado el corte -- reprocesar debe encontrarla sin crear
        // un corte nuevo ni tocar la relación que ya existía.
        $distributorB = Distributor::factory()->create(['branch_id' => $branch->id]);
        cutoffVoucher($branch, $distributorB, now()->toDateString());

        $gm = User::factory()->create();
        cutoffSignInBusinessRole($gm, 'general_manager', $branch);

        $this->postJson("/api/v1/cutoffs/{$cutoff->id}/reprocess")
            ->assertOk()
            ->assertJsonPath('data.id', $cutoff->id)
            ->assertJsonPath('data.status', 'EJECUTADO');

        $this->assertDatabaseCount('cutoffs', 1);
        $this->assertDatabaseCount('cutoff_relations', 2);
        $this->assertDatabaseHas('cutoff_relations', [
            'cutoff_id' => $cutoff->id,
            'distributor_id' => $distributorB->id,
        ]);

        // Reprocesar de nuevo sin distribuidoras nuevas no debe duplicar nada.
        $this->postJson("/api/v1/cutoffs/{$cutoff->id}/reprocess")->assertOk();
        $this->assertDatabaseCount('cutoff_relations', 2);
    });

    it('refuses to reprocess a cutoff generated before period_start existed', function (): void {
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
        $cutoff->update(['period_start' => null]);

        $gm = User::factory()->create();
        cutoffSignInBusinessRole($gm, 'general_manager', $branch);

        $this->postJson("/api/v1/cutoffs/{$cutoff->id}/reprocess")
            ->assertStatus(422);
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

    it('charges the distributor the full quincena (commission not kept) plus the late fee when a relation goes overdue', function (): void {
        // Quincena 2825.00 (ya incluye la comision de categoria, 150.00 de esos
        // 2825 son la comision -- ver primera prueba de este archivo). A tiempo
        // la distribuidora solo remite 2675.00 (2825 - 150 de comision que se
        // queda). Si no paga, ya no gana esa comision: debe remitir la quincena
        // COMPLETA (2825, sin restarle nada) MAS la multa (300) = 3125.00.
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $voucher = cutoffVoucher($branch, $distributor, now()->subDays(25)->toDateString(), 300.00);

        $manager = User::factory()->create();
        cutoffSignInBusinessRole($manager, 'branch_manager', $branch);

        $this->postJson("/api/v1/branches/{$branch->id}/cutoffs/generate", [
            'period_start' => now()->subDays(30)->toDateString(),
            'period_end' => now()->subDays(20)->toDateString(),
        ])->assertCreated();

        $relation = CutoffRelation::query()->firstOrFail();

        (new App\Services\Cutoffs\MarkOverdueRelationsService())->execute();

        $this->assertDatabaseHas('cutoff_relation_items', [
            'cutoff_relation_id' => $relation->id,
            'voucher_id' => $voucher->id,
            'is_late_payment' => 1,
            'commission_amount' => 0.00,
            'late_fee_amount' => 300.00,
            'line_total_amount' => 3125.00,
        ]);

        $this->assertDatabaseHas('cutoff_relations', [
            'id' => $relation->id,
            'status' => 'VENCIDA',
            'total_commission' => 0.00,
            'total_late_fees' => 300.00,
            'total_amount_due' => 3125.00,
        ]);
    });

    it('closes a cutoff manually, marking unpaid relations as VENCIDA even before their due date', function (): void {
        // payment_due_date de la relacion ya no lleva dias de gracia sumados:
        // es exactamente el period_end del corte (ver GenerateCutoffService).
        // Para que quede en el futuro (y probar que el cierre manual la
        // vence de todos modos, sin esperar a esa fecha), el corte tiene que
        // generarse con un period_end futuro.
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        cutoffVoucher($branch, $distributor, now()->addDays(5)->toDateString(), 300.00);

        $manager = User::factory()->create();
        cutoffSignInBusinessRole($manager, 'branch_manager', $branch);

        $this->postJson("/api/v1/branches/{$branch->id}/cutoffs/generate", [
            'period_start' => now()->toDateString(),
            'period_end' => now()->addDays(5)->toDateString(),
        ])->assertCreated();

        $cutoff = Cutoff::query()->firstOrFail();
        $relation = CutoffRelation::query()->firstOrFail();
        expect($relation->payment_due_date->isFuture())->toBeTrue();

        $gm = User::factory()->create();
        cutoffSignInBusinessRole($gm, 'general_manager', $branch);

        $this->postJson("/api/v1/cutoffs/{$cutoff->id}/close")
            ->assertOk()
            ->assertJsonPath('data.status', 'CERRADO');

        $this->assertDatabaseHas('cutoffs', ['id' => $cutoff->id, 'status' => 'CERRADO']);
        $this->assertDatabaseHas('cutoff_relations', [
            'id' => $relation->id,
            'status' => 'VENCIDA',
            'total_amount_due' => 3125.00,
        ]);

        // Ya cerrado, no se puede volver a cerrar.
        $this->postJson("/api/v1/cutoffs/{$cutoff->id}/close")->assertStatus(422);
    });

    it('lets a general manager list cutoffs of any branch, not just the one their own role is attached to', function (): void {
        // El general_manager es un rol global: activeBusinessBranchIds() solo
        // le devuelve la sucursal donde quedó su vínculo (su "matriz"), pero
        // eso no debe limitar qué sucursales puede CONSULTAR -- antes el
        // listado se filtraba siempre por esa sucursal sin importar el rol,
        // así que seleccionar otra sucursal en el frontend nunca mostraba
        // nada.
        $matriz = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();

        $distributorMatriz = Distributor::factory()->create(['branch_id' => $matriz->id]);
        cutoffVoucher($matriz, $distributorMatriz, now()->toDateString());

        $distributorOther = Distributor::factory()->create(['branch_id' => $otherBranch->id]);
        cutoffVoucher($otherBranch, $distributorOther, now()->toDateString());

        $branchManagerMatriz = User::factory()->create();
        cutoffSignInBusinessRole($branchManagerMatriz, 'branch_manager', $matriz);
        $this->postJson("/api/v1/branches/{$matriz->id}/cutoffs/generate", [
            'period_start' => now()->subDays(15)->toDateString(),
            'period_end' => now()->toDateString(),
        ])->assertCreated();

        $branchManagerOther = User::factory()->create();
        cutoffSignInBusinessRole($branchManagerOther, 'branch_manager', $otherBranch);
        $this->postJson("/api/v1/branches/{$otherBranch->id}/cutoffs/generate", [
            'period_start' => now()->subDays(15)->toDateString(),
            'period_end' => now()->toDateString(),
        ])->assertCreated();

        $matrizCutoff = Cutoff::query()->where('branch_id', $matriz->id)->firstOrFail();
        $otherCutoff = Cutoff::query()->where('branch_id', $otherBranch->id)->firstOrFail();

        $gm = User::factory()->create();
        cutoffSignInBusinessRole($gm, 'general_manager', $matriz);

        // Sin branch_id: el gerente general ve los cortes de AMBAS sucursales.
        $response = $this->getJson('/api/v1/cutoffs?per_page=50')->assertOk();
        $ids = collect($response->json('data.data'))->pluck('id')->all();
        expect($ids)->toContain($matrizCutoff->id)->toContain($otherCutoff->id);

        // Pidiendo explícitamente la sucursal que NO es la suya, la ve.
        $response = $this->getJson("/api/v1/cutoffs?per_page=50&branch_id={$otherBranch->id}")->assertOk();
        $ids = collect($response->json('data.data'))->pluck('id')->all();
        expect($ids)->toContain($otherCutoff->id)->not->toContain($matrizCutoff->id);

        // Un branch_manager, en cambio, sigue restringido a su propia
        // sucursal aunque pida el branch_id de otra.
        Sanctum::actingAs($branchManagerMatriz);
        $response = $this->getJson("/api/v1/cutoffs?per_page=50&branch_id={$otherBranch->id}")->assertOk();
        $ids = collect($response->json('data.data'))->pluck('id')->all();
        expect($ids)->toContain($matrizCutoff->id)->not->toContain($otherCutoff->id);
    });

    it('does not double-charge the late fee when a carried-over relation goes overdue again', function (): void {
        // Bug reportado: un arrastre que YA incluía la multa de su relación
        // original (porque esa relación se venció) se le volvía a sumar la
        // misma multa si la relación que lo recibió TAMBIÉN se vencía sin
        // pagarse -- la multa es una sola por vale, nunca una por cada corte
        // que sigue sin pagarse.
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        cutoffVoucher($branch, $distributor, now()->subDays(20)->toDateString(), 300.00);

        $manager = User::factory()->create();
        cutoffSignInBusinessRole($manager, 'branch_manager', $branch);

        $this->postJson("/api/v1/branches/{$branch->id}/cutoffs/generate", [
            'period_start' => now()->subDays(30)->toDateString(),
            'period_end' => now()->subDays(20)->toDateString(),
        ])->assertCreated();

        $relation1 = CutoffRelation::query()->firstOrFail();
        (new App\Services\Cutoffs\MarkOverdueRelationsService())->execute();

        $this->assertDatabaseHas('cutoff_relation_items', [
            'cutoff_relation_id' => $relation1->id,
            'late_fee_amount' => 300.00,
            'line_total_amount' => 3125.00,
        ]);

        // Se genera el siguiente corte: la deuda de relation1 (ya VENCIDA) se
        // arrastra tal cual a relation2, multa incluida.
        $this->postJson("/api/v1/branches/{$branch->id}/cutoffs/generate", [
            'period_start' => now()->subDays(19)->toDateString(),
            'period_end' => now()->subDays(10)->toDateString(),
        ])->assertCreated();

        $relation2 = CutoffRelation::query()->where('id', '!=', $relation1->id)->firstOrFail();
        expect($relation2->previous_relation_id)->toBe($relation1->id);

        $this->assertDatabaseHas('cutoff_relation_items', [
            'cutoff_relation_id' => $relation2->id,
            'origin_relation_id' => $relation1->id,
            'late_fee_amount' => 300.00,
            'line_total_amount' => 3125.00,
        ]);

        // relation2 también se vence sin pagarse -- la multa NO debe
        // duplicarse sobre el arrastre que ya la traía incluida.
        (new App\Services\Cutoffs\MarkOverdueRelationsService())->execute();

        $this->assertDatabaseHas('cutoff_relation_items', [
            'cutoff_relation_id' => $relation2->id,
            'origin_relation_id' => $relation1->id,
            'late_fee_amount' => 300.00,
            'line_total_amount' => 3125.00,
        ]);

        $this->assertDatabaseHas('cutoff_relations', [
            'id' => $relation2->id,
            'status' => 'VENCIDA',
            'total_late_fees' => 300.00,
            'total_amount_due' => 3125.00,
        ]);
    });
});
