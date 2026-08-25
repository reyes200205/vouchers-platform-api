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

    it('rejects generating a cutoff for a period that already has one in this branch', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        cutoffVoucher($branch, $distributor, now()->toDateString());

        $manager = User::factory()->create();
        cutoffSignInBusinessRole($manager, 'branch_manager', $branch);

        $payload = [
            'period_start' => now()->subDays(15)->toDateString(),
            'period_end' => now()->toDateString(),
        ];

        $this->postJson("/api/v1/branches/{$branch->id}/cutoffs/generate", $payload)->assertCreated();

        // Mismo rango de fechas otra vez -- doble clic en "Generar corte",
        // reintento de red, o simplemente pedirlo de nuevo por error.
        $this->postJson("/api/v1/branches/{$branch->id}/cutoffs/generate", $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ya existe un corte para este mismo periodo en esta sucursal.');

        $this->assertDatabaseCount('cutoffs', 1);
    });

    it('allows two different branches to generate a cutoff for the same period', function (): void {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $distributorA = Distributor::factory()->create(['branch_id' => $branchA->id]);
        $distributorB = Distributor::factory()->create(['branch_id' => $branchB->id]);
        cutoffVoucher($branchA, $distributorA, now()->toDateString());
        cutoffVoucher($branchB, $distributorB, now()->toDateString());

        $payload = [
            'period_start' => now()->subDays(15)->toDateString(),
            'period_end' => now()->toDateString(),
        ];

        $managerA = User::factory()->create();
        cutoffSignInBusinessRole($managerA, 'branch_manager', $branchA);
        $this->postJson("/api/v1/branches/{$branchA->id}/cutoffs/generate", $payload)->assertCreated();

        $managerB = User::factory()->create();
        cutoffSignInBusinessRole($managerB, 'branch_manager', $branchB);
        $this->postJson("/api/v1/branches/{$branchB->id}/cutoffs/generate", $payload)->assertCreated();

        $this->assertDatabaseCount('cutoffs', 2);
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

        // El segundo corte trae DOS cosas para este vale, no solo el
        // arrastre: al facturarse en el primer corte, su payment_due_date
        // avanzó 14 días (frequencyDays por defecto) -- eso cae dentro de la
        // ventana del segundo periodo (mañana a +15 días), así que también
        // le toca su quincena normal ahí, aparte del arrastre de la que
        // nunca se pagó (el mismo comportamiento documentado para vales
        // MOROSO en GenerateCutoffService::generateRelation). Total:
        // 2,675.00 de arrastre + 2,675.00 de la quincena nueva = 5,350.00.
        expect((float) $secondRelation->total_amount_due)->toBe(5350.00);

        $this->assertDatabaseHas('cutoff_relations', [
            'id' => $firstRelation->id,
            'status' => 'CERRADA',
        ]);

        $this->assertDatabaseHas('cutoff_relation_items', [
            'cutoff_relation_id' => $secondRelation->id,
            'origin_relation_id' => $firstRelation->id,
            'payment_amount' => 2675.00,
        ]);

        $this->assertDatabaseHas('cutoff_relation_items', [
            'cutoff_relation_id' => $secondRelation->id,
            'origin_relation_id' => null,
            'line_total_amount' => 2675.00,
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

    it('takes away 20% of the distributor\'s CURRENT points balance the moment a relation goes VENCIDA, independent of whether it ever gets paid', function (): void {
        // A diferencia del bono de puntos (SettleCutoffRelationService::awardPoints,
        // que solo se otorga si la relacion se termina PAGANDO), esta
        // penalizacion es un castigo inmediato por el simple hecho de
        // vencerse sin pagar -- una distribuidora que encadena varios cortes
        // sin pagar debe ir perdiendo puntos aunque nunca llegue a liquidar
        // nada (antes no perdia ninguno, quedaba "congelada").
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'current_points' => 10,
        ]);
        cutoffVoucher($branch, $distributor, now()->subDays(25)->toDateString(), 300.00);

        $manager = User::factory()->create();
        cutoffSignInBusinessRole($manager, 'branch_manager', $branch);

        $this->postJson("/api/v1/branches/{$branch->id}/cutoffs/generate", [
            'period_start' => now()->subDays(30)->toDateString(),
            'period_end' => now()->subDays(20)->toDateString(),
        ])->assertCreated();

        (new App\Services\Cutoffs\MarkOverdueRelationsService())->execute();

        // floor(10 * 20%) = 2 puntos menos.
        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'current_points' => 8.00,
        ]);

        $this->assertDatabaseHas('point_movements', [
            'distributor_id' => $distributor->id,
            'transaction_type' => 'PENALIZACION_ATRASO',
            'points' => -2,
        ]);
    });

    it('does not touch a distributor\'s points penalty-wise when it has zero points to begin with', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'current_points' => 0,
        ]);
        cutoffVoucher($branch, $distributor, now()->subDays(25)->toDateString(), 300.00);

        $manager = User::factory()->create();
        cutoffSignInBusinessRole($manager, 'branch_manager', $branch);

        $this->postJson("/api/v1/branches/{$branch->id}/cutoffs/generate", [
            'period_start' => now()->subDays(30)->toDateString(),
            'period_end' => now()->subDays(20)->toDateString(),
        ])->assertCreated();

        (new App\Services\Cutoffs\MarkOverdueRelationsService())->execute();

        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'current_points' => 0.00,
        ]);
        $this->assertDatabaseCount('point_movements', 0);
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

    it('refuses to reprocess a cutoff that was already closed manually, instead of silently reopening it', function (): void {
        // Bug reportado: reprocesar un corte CERRADO lo dejaba en EJECUTADO
        // otra vez -- lo "reabría" como efecto secundario de solo buscar
        // distribuidoras nuevas, sin que nadie lo pidiera explícitamente.
        // CloseCutoffService ya trata CERRADO como estado final (no se
        // puede volver a cerrar); ReprocessCutoffService ahora hace lo
        // mismo.
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        // El vencimiento se deja unos días ANTES del cierre del periodo (no
        // exactamente igual a period_end): SQLite guarda la columna `date`
        // con hora incluida y, comparado como texto contra el límite del
        // whereBetween, un vencimiento que cae justo en el límite superior
        // queda fuera por unos caracteres de más (ver otras pruebas de este
        // archivo con el mismo comentario) -- en MySQL, la columna real sí
        // es DATE y trunca la hora, así que ese caso no se da en producción.
        cutoffVoucher($branch, $distributor, now()->subDays(3)->toDateString());

        $manager = User::factory()->create();
        cutoffSignInBusinessRole($manager, 'branch_manager', $branch);

        $this->postJson("/api/v1/branches/{$branch->id}/cutoffs/generate", [
            'period_start' => now()->subDays(15)->toDateString(),
            'period_end' => now()->toDateString(),
        ])->assertCreated();

        $cutoff = Cutoff::query()->firstOrFail();

        $gm = User::factory()->create();
        cutoffSignInBusinessRole($gm, 'general_manager', $branch);

        $this->postJson("/api/v1/cutoffs/{$cutoff->id}/close")
            ->assertOk()
            ->assertJsonPath('data.status', 'CERRADO');

        // Una distribuidora nueva aparece después del cierre -- ni así debe
        // reprocesarse: un corte cerrado ya es un estado final.
        $distributorLate = Distributor::factory()->create(['branch_id' => $branch->id]);
        cutoffVoucher($branch, $distributorLate, now()->subDays(3)->toDateString());

        $this->postJson("/api/v1/cutoffs/{$cutoff->id}/reprocess")->assertStatus(422);

        $this->assertDatabaseHas('cutoffs', ['id' => $cutoff->id, 'status' => 'CERRADO']);
        $this->assertDatabaseCount('cutoff_relations', 1);
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

    it('keeps adding another full late fee to the FINAL installment each time the carried-over relation goes overdue again', function (): void {
        // Revision del profesor: SOLO la ULTIMA quincena de un vale (la que
        // se queda congelada arrastrandose -- ver GenerateCutoffService) va
        // acumulando multa cada corte que pasa sin pagarse. Aqui el vale
        // tiene una sola quincena (total_fortnights = 1), asi que su unica
        // quincena YA es la final desde el principio.
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $voucher = cutoffVoucher($branch, $distributor, now()->subDays(20)->toDateString(), 300.00);
        $voucher->update(['total_fortnights' => 1]);

        $manager = User::factory()->create();
        cutoffSignInBusinessRole($manager, 'branch_manager', $branch);

        $this->postJson("/api/v1/branches/{$branch->id}/cutoffs/generate", [
            'period_start' => now()->subDays(30)->toDateString(),
            'period_end' => now()->subDays(20)->toDateString(),
        ])->assertCreated();

        $relation1 = CutoffRelation::query()->firstOrFail();
        $item1 = $relation1->items()->firstOrFail();
        expect($item1->installment_number)->toBe($item1->total_payments); // es la final (1/1)

        (new App\Services\Cutoffs\MarkOverdueRelationsService())->execute();

        // Primera vez que se vence: una multa ($300) y se registra una
        // quincena de comision perdida ($1,200 -- distributor_profit_amount
        // 1200 / total_fortnights 1). La comision perdida de la primera vez
        // NO se suma aparte al total -- ya viene incluida en la quincena
        // entera del vale ($22,600 / 1 = $22,600), asi que el total es
        // $22,600 + $300 de multa = $22,900.
        $this->assertDatabaseHas('cutoff_relation_items', [
            'cutoff_relation_id' => $relation1->id,
            'late_fee_amount' => 300.00,
            'commission_forfeited_amount' => 1200.00,
            'line_total_amount' => 22900.00,
        ]);

        // Se genera el siguiente corte: la deuda de relation1 (ya VENCIDA) se
        // arrastra tal cual a relation2, multa y comision perdida incluidas.
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
            'commission_forfeited_amount' => 1200.00,
            'line_total_amount' => 22900.00,
        ]);

        // relation2 TAMBIEN se vence sin pagarse -- como sigue siendo la
        // MISMA ultima quincena (nunca se genero una "2/1"), esta vez SI se
        // le suma otra multa ($300) Y otra comision perdida ($1,200)
        // completas encima de lo que ya traia: $600 de multa acumulada,
        // $2,400 de comision perdida acumulada, $22,900 + $300 + $1,200 =
        // $24,400 en total.
        (new App\Services\Cutoffs\MarkOverdueRelationsService())->execute();

        $this->assertDatabaseHas('cutoff_relation_items', [
            'cutoff_relation_id' => $relation2->id,
            'origin_relation_id' => $relation1->id,
            'late_fee_amount' => 600.00,
            'commission_forfeited_amount' => 2400.00,
            'line_total_amount' => 24400.00,
        ]);

        $this->assertDatabaseHas('cutoff_relations', [
            'id' => $relation2->id,
            'status' => 'VENCIDA',
            'total_late_fees' => 600.00,
            'total_amount_due' => 24400.00,
        ]);
    });

    it('does NOT keep adding late fees to a non-final installment that falls behind while the voucher keeps billing later quincenas', function (): void {
        // Aclaracion del usuario: la multa acumulativa (prueba anterior)
        // es SOLO para la ultima quincena del vale, la que se queda
        // congelada arrastrandose. Una quincena que NO es la ultima (aqui,
        // la 1/8 de un vale de 8) es una deuda aparte y distinta -- si
        // tambien se le fuera sumando multa cada corte que sigue sin
        // pagarse, cada quincena atrasada del vale terminaria multiplicando
        // su propia multa por separado. Para esas se mantiene la regla
        // original: una sola multa, nunca se duplica.
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        // total_fortnights = 8 (default de cutoffVoucher): la quincena 1/8
        // que se va a vencer aqui NO es la ultima del vale.
        cutoffVoucher($branch, $distributor, now()->subDays(20)->toDateString(), 300.00);

        $manager = User::factory()->create();
        cutoffSignInBusinessRole($manager, 'branch_manager', $branch);

        $this->postJson("/api/v1/branches/{$branch->id}/cutoffs/generate", [
            'period_start' => now()->subDays(30)->toDateString(),
            'period_end' => now()->subDays(20)->toDateString(),
        ])->assertCreated();

        $relation1 = CutoffRelation::query()->firstOrFail();
        $item1 = $relation1->items()->firstOrFail();
        expect($item1->installment_number)->not->toBe($item1->total_payments); // 1 !== 8, NO es la final

        (new App\Services\Cutoffs\MarkOverdueRelationsService())->execute();

        $this->assertDatabaseHas('cutoff_relation_items', [
            'cutoff_relation_id' => $relation1->id,
            'late_fee_amount' => 300.00,
            'line_total_amount' => 3125.00,
        ]);

        $this->postJson("/api/v1/branches/{$branch->id}/cutoffs/generate", [
            'period_start' => now()->subDays(19)->toDateString(),
            'period_end' => now()->subDays(10)->toDateString(),
        ])->assertCreated();

        $relation2 = CutoffRelation::query()->where('id', '!=', $relation1->id)->firstOrFail();
        $carried = $relation2->items()->where('origin_relation_id', $relation1->id)->firstOrFail();
        expect($carried->installment_number)->not->toBe($carried->total_payments);

        // relation2 TAMBIEN se vence sin pagarse -- como esta quincena
        // arrastrada NO es la final del vale, ni la multa ni la comision
        // perdida se duplican ($300 de multa, $3,125 en total, ambos
        // congelados igual que la primera vez).
        (new App\Services\Cutoffs\MarkOverdueRelationsService())->execute();

        $this->assertDatabaseHas('cutoff_relation_items', [
            'id' => $carried->id,
            'late_fee_amount' => 300.00,
            'commission_forfeited_amount' => 150.00,
            'line_total_amount' => 3125.00,
        ]);
    });

    it('never generates a new installment past the voucher total_fortnights once it is fully billed and unpaid', function (): void {
        // Escenario reportado: un vale de 1 sola quincena (total_fortnights)
        // que llega a su ultima quincena sin pagarse. El vale SI avanza su
        // payment_due_date internamente al facturarse (payment_due_date +
        // payment_frequency_days, 14 dias por default), pero ya no tiene mas
        // quincenas reales -- un corte cuyo periodo cubra esa fecha
        // avanzada NO debe crear una quincena "2/1" fantasma. Solo debe
        // traer el arrastre de esa MISMA ultima quincena.
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $voucher = cutoffVoucher($branch, $distributor, now()->subDays(20)->toDateString(), 300.00);
        $voucher->update(['total_fortnights' => 1]);

        $manager = User::factory()->create();
        cutoffSignInBusinessRole($manager, 'branch_manager', $branch);

        $this->postJson("/api/v1/branches/{$branch->id}/cutoffs/generate", [
            'period_start' => now()->subDays(30)->toDateString(),
            'period_end' => now()->subDays(20)->toDateString(),
        ])->assertCreated();

        $relation1 = CutoffRelation::query()->firstOrFail();
        $originalItem = $relation1->items()->firstOrFail();
        expect($originalItem->installment_number)->toBe(1);

        (new App\Services\Cutoffs\MarkOverdueRelationsService())->execute();

        $voucher->refresh();
        expect($voucher->installments_billed)->toBe(1);
        // payment_due_date avanzo (dueDate + payment_frequency_days, 14 por
        // default: now-20 + 14 = now-6), aunque el vale ya no tenga mas
        // quincenas reales.
        $this->assertSame(now()->subDays(6)->toDateString(), $voucher->payment_due_date->toDateString());

        // El siguiente corte, consecutivo, con un periodo que SI cubre esa
        // fecha avanzada (now-6) -- si el filtro no existiera, aqui se
        // crearia una quincena "2/1" fantasma ademas del arrastre.
        $this->postJson("/api/v1/branches/{$branch->id}/cutoffs/generate", [
            'period_start' => now()->subDays(19)->toDateString(),
            'period_end' => now()->toDateString(),
        ])->assertCreated();

        $relation2 = CutoffRelation::query()->where('id', '!=', $relation1->id)->firstOrFail();

        // Un solo item en relation2 (el arrastre), con el MISMO
        // installment_number que ya traia -- no un item nuevo con el
        // siguiente numero de quincena.
        $this->assertSame(1, $relation2->items()->count());
        $carriedItem = $relation2->items()->firstOrFail();
        $this->assertSame($originalItem->installment_number, $carriedItem->installment_number);
        $this->assertNotNull($carriedItem->origin_relation_id);
    });
});
