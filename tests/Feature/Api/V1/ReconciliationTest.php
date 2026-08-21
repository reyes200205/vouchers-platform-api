<?php

declare(strict_types=1);

use App\Enums\CutoffRelationStatus;
use App\Enums\ReconciliationStatus;
use App\Models\BankTransaction;
use App\Models\Branch;
use App\Models\CutoffRelation;
use App\Models\Distributor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function reconciliationSignIn(User $user, string $roleCode, Branch $branch): void
{
    $role = Role::query()->firstOrCreate(['code' => $roleCode], ['name' => $roleCode]);
    $user->businessRoles()->attach($role, [
        'branch_id' => $branch->id,
        'assigned_at' => now(),
        'is_primary' => true,
    ]);
    Sanctum::actingAs($user);
}

function reconciliationOpenRelation(Branch $branch, Distributor $distributor, float $amountDue = 2599.00): CutoffRelation
{
    return CutoffRelation::query()->create([
        'cutoff_id' => \App\Models\Cutoff::factory()->create(['branch_id' => $branch->id])->id,
        'distributor_id' => $distributor->id,
        'relation_number' => 'REL-TEST-' . fake()->unique()->numberBetween(1000, 9999),
        'payment_reference' => 'REF-TEST' . fake()->unique()->numberBetween(1000, 9999),
        'payment_due_date' => now()->addDays(10)->toDateString(),
        'credit_limit_snapshot' => 20000,
        'available_credit_snapshot' => 20000,
        'total_payment' => 2599.00,
        'total_commission' => 226.00,
        'total_late_fees' => 0.00,
        'total_amount_due' => $amountDue,
        'status' => CutoffRelationStatus::GENERADA,
        'generated_at' => now(),
    ]);
}

describe('Reconciliations', function (): void {
    it('imports a CSV with deposits and auto-matches by reference', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'available_credit' => 10000,
            'credit_limit' => 20000,
        ]);
        $relation = reconciliationOpenRelation($branch, $distributor);

        $csv = "fecha,referencia,concepto,importe\n"
            . now()->subDay()->format('Y-m-d') . ",{$relation->payment_reference},Pago de corte,2599.00\n"
            . now()->subDay()->format('Y-m-d') . ',SIN-REFERENCIA,Deposito sin match,500.00';

        $file = UploadedFile::fake()->createWithContent('estado-cuenta.csv', $csv);

        $cashier = User::factory()->create();
        reconciliationSignIn($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/branches/{$branch->id}/reconciliations/import", [
            'file' => $file,
        ])->assertCreated()
            ->assertJsonPath('data.import.row_count', 2)
            ->assertJsonPath('data.import.error_count', 0)
            ->assertJsonPath('data.auto_matched', 1);

        $this->assertDatabaseCount('bank_transactions', 2);
        $this->assertDatabaseCount('distributor_payments', 1);
        $this->assertDatabaseCount('reconciliations', 1);

        // Cada transacción importada debe quedar ligada a la sucursal del
        // import: antes no se guardaba y una conciliación manual posterior
        // sobre esa transacción podía dar "Forbidden" (ver
        // EnsureBusinessAbility/ImportBankDepositsService).
        $this->assertDatabaseHas('bank_transactions', [
            'reference' => $relation->payment_reference,
            'branch_id' => $branch->id,
        ]);
        $this->assertDatabaseHas('bank_transactions', [
            'reference' => 'SIN-REFERENCIA',
            'branch_id' => $branch->id,
        ]);

        $this->assertDatabaseHas('reconciliations', [
            'status' => 'CONCILIADA',
            'reconciled_amount' => 2599.00,
            'amount_difference' => 0.00,
        ]);

        $this->assertDatabaseHas('cutoff_relations', [
            'id' => $relation->id,
            'status' => 'PAGADA',
        ]);

        // El crédito disponible ya no se libera por el monto conciliado de la
        // relación: se libera por vale, completo, hasta que ese vale termina de
        // pagarse (ver SettleCutoffRelationService). Esta relación de prueba no
        // tiene ningún vale/CutoffRelationItem detrás, así que no hay nada que
        // liquidar y el crédito disponible se queda igual.
        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'available_credit' => 10000.00,
        ]);
    });

    it('imports a bank export that uses real-world Spanish column names instead of the plain ones', function (): void {
        // Reproduce el archivo real que reportó el usuario: encabezados como
        // "Fecha de pago" y "Pago" (en vez de "fecha"/"importe" a secas), que
        // antes no se reconocían y hacían fallar TODAS las filas con "Ninguna
        // fila del archivo pudo ser importada".
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'available_credit' => 10000,
            'credit_limit' => 20000,
        ]);
        $relation = reconciliationOpenRelation($branch, $distributor);

        $csv = "Concepto,Referencia,Pago,Fecha de pago\n"
            . "Abono a referencia,{$relation->payment_reference}," . '2599.00,' . now()->subDay()->format('Y-m-d') . "\n"
            . 'Deposito sin match,SIN-REFERENCIA,500.00,' . now()->subDay()->format('Y-m-d');

        $file = UploadedFile::fake()->createWithContent('estado-cuenta.csv', $csv);

        $cashier = User::factory()->create();
        reconciliationSignIn($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/branches/{$branch->id}/reconciliations/import", [
            'file' => $file,
        ])->assertCreated()
            ->assertJsonPath('data.import.row_count', 2)
            ->assertJsonPath('data.import.error_count', 0)
            ->assertJsonPath('data.auto_matched', 1);

        $this->assertDatabaseHas('cutoff_relations', [
            'id' => $relation->id,
            'status' => 'PAGADA',
        ]);
    });

    it('flags a difference when the deposit amount does not match the relation', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'available_credit' => 10000,
        ]);
        $relation = reconciliationOpenRelation($branch, $distributor, 2599.00);

        $csv = 'fecha,referencia,concepto,importe' . "\n"
            . now()->subDay()->format('Y-m-d') . ",{$relation->payment_reference},Pago parcial,1500.00";

        $file = UploadedFile::fake()->createWithContent('estado-cuenta.csv', $csv);

        $cashier = User::factory()->create();
        reconciliationSignIn($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/branches/{$branch->id}/reconciliations/import", [
            'file' => $file,
        ])->assertCreated()
            ->assertJsonPath('data.auto_matched', 1);

        $this->assertDatabaseHas('reconciliations', [
            'status' => 'CON_DIFERENCIA',
            'amount_difference' => -1099.00,
        ]);

        $this->assertDatabaseHas('cutoff_relations', [
            'id' => $relation->id,
            'status' => 'PARCIAL',
        ]);

        // Un pago parcial no liquida ningún vale, así que no libera crédito.
        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'available_credit' => 10000.00,
        ]);
    });

    it('rejects a file that was already imported', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $relation = reconciliationOpenRelation($branch, $distributor);

        $csv = 'fecha,referencia,concepto,importe' . "\n"
            . now()->subDay()->format('Y-m-d') . ",{$relation->payment_reference},Pago de corte,2599.00";

        $file = UploadedFile::fake()->createWithContent('estado-cuenta.csv', $csv);

        $cashier = User::factory()->create();
        reconciliationSignIn($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/branches/{$branch->id}/reconciliations/import", ['file' => $file])->assertCreated();
        $this->postJson("/api/v1/branches/{$branch->id}/reconciliations/import", ['file' => $file])->assertStatus(422);
    });

    it('requires a second user to verify a manual match', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'available_credit' => 10000,
        ]);
        $relation = reconciliationOpenRelation($branch, $distributor);

        $transaction = BankTransaction::query()->create([
            'reference' => 'REF-MANUAL',
            'transaction_date' => now()->subDay()->toDateString(),
            'amount' => 2599.00,
            'transaction_type' => 'DEPOSITO',
        ]);

        $gm = User::factory()->create();
        reconciliationSignIn($gm, 'general_manager', $branch);

        $this->postJson("/api/v1/reconciliations/bank-transactions/{$transaction->id}/manual-match", [
            'cutoff_relation_id' => $relation->id,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'PENDIENTE_VERIFICACION');

        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'available_credit' => 10000.00,
        ]);

        $this->postJson("/api/v1/reconciliations/1/verify")
            ->assertStatus(422);

        $branchManager = User::factory()->create();
        reconciliationSignIn($branchManager, 'branch_manager', $branch);

        $this->postJson("/api/v1/reconciliations/1/verify")
            ->assertOk()
            ->assertJsonPath('data.status', 'CONCILIADA')
            ->assertJsonPath('data.verified_by_user_id', $branchManager->id);

        $this->assertDatabaseHas('cutoff_relations', [
            'id' => $relation->id,
            'status' => 'PAGADA',
        ]);

        // Igual que en el import automático: sin vale/CutoffRelationItem detrás
        // de esta relación de prueba no hay nada que liquidar, así que el
        // crédito disponible no cambia.
        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'available_credit' => 10000.00,
        ]);
    });

    it('lets a branch manager reject a pending manual match, freeing the bank transaction to try again', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'available_credit' => 10000,
        ]);
        $wrongRelation = reconciliationOpenRelation($branch, $distributor, 2599.00);
        $correctRelation = reconciliationOpenRelation($branch, $distributor, 2599.00);

        $transaction = BankTransaction::query()->create([
            'reference' => 'REF-MANUAL',
            'transaction_date' => now()->subDay()->toDateString(),
            'amount' => 2599.00,
            'transaction_type' => 'DEPOSITO',
        ]);

        $cashier = User::factory()->create();
        reconciliationSignIn($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/reconciliations/bank-transactions/{$transaction->id}/manual-match", [
            'cutoff_relation_id' => $wrongRelation->id,
        ])->assertCreated();

        $branchManager = User::factory()->create();
        reconciliationSignIn($branchManager, 'branch_manager', $branch);

        $this->postJson('/api/v1/reconciliations/1/reject', [
            'rejection_reason' => 'La cajera seleccionó la relación equivocada.',
        ])->assertOk();

        $this->assertDatabaseCount('reconciliations', 0);
        $this->assertDatabaseCount('distributor_payments', 0);

        // La relación que sí eligió mal nunca se tocó (verify() es quien
        // marca la relación como PAGADA/PARCIAL, y el rechazo nunca llegó a
        // ejecutar eso).
        $this->assertDatabaseHas('cutoff_relations', [
            'id' => $wrongRelation->id,
            'status' => 'GENERADA',
        ]);

        Sanctum::actingAs($cashier);

        // La transacción bancaria vuelve a estar disponible: antes del
        // rechazo, el índice único sobre bank_transaction_id la hubiera
        // dejado bloqueada para siempre.
        $this->postJson("/api/v1/reconciliations/bank-transactions/{$transaction->id}/manual-match", [
            'cutoff_relation_id' => $correctRelation->id,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'PENDIENTE_VERIFICACION');
    });

    it('requires a rejection reason', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $relation = reconciliationOpenRelation($branch, $distributor);

        $transaction = BankTransaction::query()->create([
            'reference' => 'REF-MANUAL',
            'transaction_date' => now()->subDay()->toDateString(),
            'amount' => 2599.00,
            'transaction_type' => 'DEPOSITO',
        ]);

        $cashier = User::factory()->create();
        reconciliationSignIn($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/reconciliations/bank-transactions/{$transaction->id}/manual-match", [
            'cutoff_relation_id' => $relation->id,
        ])->assertCreated();

        $branchManager = User::factory()->create();
        reconciliationSignIn($branchManager, 'branch_manager', $branch);

        $this->postJson('/api/v1/reconciliations/1/reject', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rejection_reason');

        $this->assertDatabaseCount('reconciliations', 1);
    });

    it('forbids the same user who registered the match from rejecting it', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $relation = reconciliationOpenRelation($branch, $distributor);

        $transaction = BankTransaction::query()->create([
            'reference' => 'REF-MANUAL',
            'transaction_date' => now()->subDay()->toDateString(),
            'amount' => 2599.00,
            'transaction_type' => 'DEPOSITO',
        ]);

        // El general_manager registra la conciliación manual él mismo (igual
        // que el test "requires a second user to verify") y luego intenta
        // resolver su propia propuesta -- la segunda autorización, sea para
        // aprobar o para rechazar, la debe hacer alguien más.
        $generalManager = User::factory()->create();
        reconciliationSignIn($generalManager, 'general_manager', $branch);

        $this->postJson("/api/v1/reconciliations/bank-transactions/{$transaction->id}/manual-match", [
            'cutoff_relation_id' => $relation->id,
        ])->assertCreated();

        $this->postJson('/api/v1/reconciliations/1/reject', [
            'rejection_reason' => 'Motivo de prueba',
        ])->assertStatus(422);

        $this->assertDatabaseCount('reconciliations', 1);
    });

    it('releases the distributor credit by the voucher principal once it is fully settled through reconciliation', function (): void {
        // Vale de una sola quincena (total_fortnights = 1): el primer pago ya lo
        // deja PAGADO, que es el único momento en que se libera crédito -- y se
        // libera por el PRINCIPAL del vale (10,000), no por lo que pagó la
        // distribuidora en la relación (9,500, que ya trae la comisión restada).
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'credit_limit' => 20000,
            'available_credit' => 10000, // ya usó 10,000 de principal en este vale
        ]);
        $voucher = \App\Models\Voucher::factory()->create([
            'branch_id' => $branch->id,
            'distributor_id' => $distributor->id,
            'status' => \App\Enums\VoucherStatus::ACTIVO,
            'amount' => 10000.00,
            'payment_due_date' => now()->addDays(10)->toDateString(),
            'distributor_profit_amount' => 500.00,
            'total_debt_amount' => 9500.00,
            'fortnightly_payment_amount' => 9500.00,
            'total_fortnights' => 1,
            'payments_made' => 0,
            'current_balance' => 9500.00,
        ]);

        $cutoff = \App\Models\Cutoff::factory()->create(['branch_id' => $branch->id]);
        $relation = CutoffRelation::query()->create([
            'cutoff_id' => $cutoff->id,
            'distributor_id' => $distributor->id,
            'relation_number' => 'REL-TEST-SETTLE',
            'payment_reference' => 'REF-TEST-SETTLE',
            'payment_due_date' => now()->addDays(10)->toDateString(),
            'credit_limit_snapshot' => 20000,
            'available_credit_snapshot' => 10000,
            'total_payment' => 9500.00,
            'total_commission' => 0.00,
            'total_late_fees' => 0.00,
            'total_amount_due' => 9500.00,
            'status' => CutoffRelationStatus::GENERADA,
            'generated_at' => now(),
        ]);
        \App\Models\CutoffRelationItem::query()->create([
            'cutoff_relation_id' => $relation->id,
            'voucher_id' => $voucher->id,
            'customer_id' => $voucher->customer_id,
            'product_name_snapshot' => 'Producto',
            'payments_made' => 0,
            'total_payments' => 1,
            'is_late_payment' => false,
            'installment_number' => 1,
            'accumulated_late_installments' => 0,
            'commission_amount' => 0.00,
            'payment_amount' => 9500.00,
            'late_fee_amount' => 0.00,
            'line_total_amount' => 9500.00,
        ]);

        $csv = "fecha,referencia,concepto,importe\n"
            . now()->subDay()->format('Y-m-d') . ",{$relation->payment_reference},Pago de corte,9500.00";

        $file = UploadedFile::fake()->createWithContent('estado-cuenta.csv', $csv);

        $cashier = User::factory()->create();
        reconciliationSignIn($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/branches/{$branch->id}/reconciliations/import", [
            'file' => $file,
        ])->assertCreated()->assertJsonPath('data.auto_matched', 1);

        $this->assertDatabaseHas('vouchers', ['id' => $voucher->id, 'status' => 'PAGADO']);

        // 10,000 (disponible antes) + 10,000 (principal liberado) = 20,000.
        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'available_credit' => 20000.00,
        ]);
    });

    it('releases the distributor credit incrementally per fortnight settled, not the full principal at once', function (): void {
        // Vale de $15,000 a 8 quincenas: cada quincena liquidada debe liberar
        // 15,000 / 8 = $1,875 de crédito -- ni la quincena completa que pagó la
        // distribuidora (que trae intereses/seguro/comisión mezclados), ni el
        // principal completo de golpe hasta que se termine de pagar el vale.
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'credit_limit' => 30000,
            'available_credit' => 15000, // ya usó 15,000 de principal en este vale
        ]);
        $voucher = \App\Models\Voucher::factory()->create([
            'branch_id' => $branch->id,
            'distributor_id' => $distributor->id,
            'status' => \App\Enums\VoucherStatus::ACTIVO,
            'amount' => 15000.00,
            'payment_due_date' => now()->addDays(10)->toDateString(),
            'distributor_profit_amount' => 900.00,
            'total_debt_amount' => 20200.00,
            'fortnightly_payment_amount' => 2525.00,
            'total_fortnights' => 8,
            'payments_made' => 0,
            'current_balance' => 20200.00,
        ]);

        $cutoff = \App\Models\Cutoff::factory()->create(['branch_id' => $branch->id]);
        $relation = CutoffRelation::query()->create([
            'cutoff_id' => $cutoff->id,
            'distributor_id' => $distributor->id,
            'relation_number' => 'REL-TEST-PARTIAL',
            'payment_reference' => 'REF-TEST-PARTIAL',
            'payment_due_date' => now()->addDays(10)->toDateString(),
            'credit_limit_snapshot' => 30000,
            'available_credit_snapshot' => 15000,
            'total_payment' => 2525.00,
            'total_commission' => 0.00,
            'total_late_fees' => 0.00,
            'total_amount_due' => 2525.00,
            'status' => CutoffRelationStatus::GENERADA,
            'generated_at' => now(),
        ]);
        \App\Models\CutoffRelationItem::query()->create([
            'cutoff_relation_id' => $relation->id,
            'voucher_id' => $voucher->id,
            'customer_id' => $voucher->customer_id,
            'product_name_snapshot' => 'Producto',
            'payments_made' => 0,
            'total_payments' => 8,
            'is_late_payment' => false,
            'installment_number' => 1,
            'accumulated_late_installments' => 0,
            'commission_amount' => 0.00,
            'payment_amount' => 2525.00,
            'late_fee_amount' => 0.00,
            'line_total_amount' => 2525.00,
        ]);

        $csv = "fecha,referencia,concepto,importe\n"
            . now()->subDay()->format('Y-m-d') . ",{$relation->payment_reference},Pago de corte,2525.00";

        $file = UploadedFile::fake()->createWithContent('estado-cuenta.csv', $csv);

        $cashier = User::factory()->create();
        reconciliationSignIn($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/branches/{$branch->id}/reconciliations/import", [
            'file' => $file,
        ])->assertCreated()->assertJsonPath('data.auto_matched', 1);

        $this->assertDatabaseHas('vouchers', ['id' => $voucher->id, 'status' => 'ACTIVO', 'payments_made' => 1]);

        // 15,000 (disponible antes) + 1,875 (1/8 del principal) = 16,875 -- NO
        // 30,000, que sería liberar el principal completo de una sola quincena.
        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'available_credit' => 16875.00,
        ]);
    });

    it('cascades a retroactive correction through the whole carryover chain when the real deposit was on time', function (): void {
        // Reproduce el bug que reporto el usuario: la cajera nunca registro el
        // deposito real de la distribuidora (aunque si llego A TIEMPO), asi que
        // la relacion se vencio, le sumo multa/le quito comision, y esa deuda ya
        // se arrastro a un segundo corte -- la relacion original quedo CERRADA.
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'credit_limit' => 30000,
            'available_credit' => 8000,
            'current_points' => 0,
        ]);

        // Vale A: la quincena que se vencio por error y se arrastro.
        $voucherA = \App\Models\Voucher::factory()->create([
            'branch_id' => $branch->id,
            'distributor_id' => $distributor->id,
            'status' => \App\Enums\VoucherStatus::MOROSO,
            'amount' => 8000.00,
            'distributor_profit_amount' => 400.00,
            'total_debt_amount' => 9200.00,
            'fortnightly_payment_amount' => 4600.00,
            'total_fortnights' => 2,
            'payments_made' => 0,
            'current_balance' => 9200.00,
        ]);

        // Vale B: la quincena normal del segundo corte, nunca atrasada.
        $voucherB = \App\Models\Voucher::factory()->create([
            'branch_id' => $branch->id,
            'distributor_id' => $distributor->id,
            'status' => \App\Enums\VoucherStatus::ACTIVO,
            'amount' => 6000.00,
            'distributor_profit_amount' => 300.00,
            'total_debt_amount' => 6000.00,
            'fortnightly_payment_amount' => 2000.00,
            'total_fortnights' => 3,
            'payments_made' => 0,
            'current_balance' => 6000.00,
        ]);

        $cutoff1 = \App\Models\Cutoff::factory()->create(['branch_id' => $branch->id]);
        $original = CutoffRelation::query()->create([
            'cutoff_id' => $cutoff1->id,
            'distributor_id' => $distributor->id,
            'relation_number' => 'REL-TEST-ORIGINAL',
            'payment_reference' => 'REF-TEST-ORIGINAL',
            'payment_due_date' => '2026-01-14',
            'early_payment_start_date' => '2026-01-01',
            'early_payment_end_date' => '2026-01-14',
            'credit_limit_snapshot' => 30000,
            'available_credit_snapshot' => 8000,
            'total_payment' => 4600.00,
            'total_commission' => 0.00,
            'total_late_fees' => 100.00,
            'total_amount_due' => 4700.00,
            'status' => CutoffRelationStatus::CERRADA,
            'closed_by_carryover_at' => now(),
            'generated_at' => now(),
        ]);
        \App\Models\CutoffRelationItem::query()->create([
            'cutoff_relation_id' => $original->id,
            'voucher_id' => $voucherA->id,
            'customer_id' => $voucherA->customer_id,
            'product_name_snapshot' => 'Producto A',
            'payments_made' => 0,
            'total_payments' => 2,
            'is_late_payment' => true,
            'installment_number' => 1,
            'accumulated_late_installments' => 1,
            'commission_amount' => 0.00,
            'payment_amount' => 4600.00,
            'late_fee_amount' => 100.00,
            'line_total_amount' => 4700.00,
        ]);

        $cutoff2 = \App\Models\Cutoff::factory()->create(['branch_id' => $branch->id]);
        $tip = CutoffRelation::query()->create([
            'cutoff_id' => $cutoff2->id,
            'distributor_id' => $distributor->id,
            'previous_relation_id' => $original->id,
            'relation_number' => 'REL-TEST-TIP',
            'payment_reference' => 'REF-TEST-TIP',
            'payment_due_date' => now()->addDays(10)->toDateString(),
            'credit_limit_snapshot' => 30000,
            'available_credit_snapshot' => 8000,
            'total_payment' => 2000.00,
            'total_commission' => 100.00,
            'total_late_fees' => 100.00,
            'total_amount_due' => 6600.00,
            'status' => CutoffRelationStatus::VENCIDA,
            'generated_at' => now(),
        ]);
        $carryoverItem = \App\Models\CutoffRelationItem::query()->create([
            'cutoff_relation_id' => $tip->id,
            'voucher_id' => $voucherA->id,
            'customer_id' => $voucherA->customer_id,
            'product_name_snapshot' => 'Producto A',
            'payments_made' => 0,
            'total_payments' => 2,
            'is_late_payment' => true,
            'installment_number' => 1,
            'accumulated_late_installments' => 1,
            'commission_amount' => 0.00,
            'payment_amount' => 4700.00,
            'late_fee_amount' => 100.00,
            'line_total_amount' => 4700.00,
            'origin_cutoff_id' => $cutoff1->id,
            'origin_relation_id' => $original->id,
        ]);
        \App\Models\CutoffRelationItem::query()->create([
            'cutoff_relation_id' => $tip->id,
            'voucher_id' => $voucherB->id,
            'customer_id' => $voucherB->customer_id,
            'product_name_snapshot' => 'Producto B',
            'payments_made' => 0,
            'total_payments' => 3,
            'is_late_payment' => false,
            'installment_number' => 1,
            'accumulated_late_installments' => 0,
            'commission_amount' => 100.00,
            'payment_amount' => 2000.00,
            'late_fee_amount' => 0.00,
            'line_total_amount' => 1900.00,
        ]);

        // El deposito real (segun el Excel del banco) cayo dentro de la
        // ventana "a tiempo" de la relacion original (1-14 de enero).
        $transaction = BankTransaction::query()->create([
            'reference' => 'REF-EXCEL-REAL',
            'transaction_date' => '2026-01-10',
            'amount' => 6300.00,
            'transaction_type' => 'DEPOSITO',
        ]);

        $cashier = User::factory()->create();
        reconciliationSignIn($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/reconciliations/bank-transactions/{$transaction->id}/manual-match", [
            'cutoff_relation_id' => $original->id,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'PENDIENTE_VERIFICACION')
            ->assertJsonPath('data.is_retroactive_correction', true);

        $branchManager = User::factory()->create();
        reconciliationSignIn($branchManager, 'branch_manager', $branch);

        $this->postJson('/api/v1/reconciliations/1/verify')
            ->assertOk()
            ->assertJsonPath('data.status', 'CONCILIADA')
            ->assertJsonPath('data.amount_difference', '0.00');

        // La relacion original (CERRADA) tambien queda corregida para que su
        // historial ya no muestre la multa, aunque el pago se aplico al
        // extremo vivo de la cadena.
        $this->assertDatabaseHas('cutoff_relations', [
            'id' => $original->id,
            'status' => 'CERRADA',
            'total_late_fees' => 0.00,
            'total_amount_due' => 4400.00,
        ]);

        $this->assertDatabaseHas('cutoff_relations', [
            'id' => $tip->id,
            'status' => 'PAGADA',
            'total_late_fees' => 0.00,
            'total_amount_due' => 6300.00,
        ]);

        $this->assertDatabaseHas('cutoff_relation_items', [
            'id' => $carryoverItem->id,
            'is_late_payment' => false,
            'commission_amount' => 200.00,
            'late_fee_amount' => 0.00,
            'payment_amount' => 4400.00,
            'line_total_amount' => 4400.00,
        ]);

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucherA->id,
            'status' => 'ACTIVO',
            'payments_made' => 1,
            'current_balance' => 4800.00,
        ]);
        $this->assertDatabaseHas('vouchers', [
            'id' => $voucherB->id,
            'status' => 'ACTIVO',
            'payments_made' => 1,
            'current_balance' => 4100.00,
        ]);

        // 8,000 (disponible antes) + 4,000 (1/2 del principal del vale A) +
        // 2,000 (1/3 del principal del vale B) = 14,000.
        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'available_credit' => 14000.00,
        ]);

        // Sin multa, la relacion ya no cuenta como "fuera de tiempo": los
        // puntos se otorgan completos (GANADO_ANTICIPADO), no con el -20%.
        $this->assertDatabaseHas('point_movements', [
            'distributor_id' => $distributor->id,
            'transaction_type' => 'GANADO_ANTICIPADO',
            'points' => 3,
        ]);
    });

    it('keeps the late fee when the real deposit date falls outside the on-time window', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'credit_limit' => 20000,
            'available_credit' => 5000,
        ]);

        $voucher = \App\Models\Voucher::factory()->create([
            'branch_id' => $branch->id,
            'distributor_id' => $distributor->id,
            'status' => \App\Enums\VoucherStatus::MOROSO,
            'amount' => 8000.00,
            'distributor_profit_amount' => 400.00,
            'total_debt_amount' => 9200.00,
            'fortnightly_payment_amount' => 4600.00,
            'total_fortnights' => 2,
            'payments_made' => 0,
            'current_balance' => 9200.00,
        ]);

        $cutoff = \App\Models\Cutoff::factory()->create(['branch_id' => $branch->id]);
        $relation = CutoffRelation::query()->create([
            'cutoff_id' => $cutoff->id,
            'distributor_id' => $distributor->id,
            'relation_number' => 'REL-TEST-STILLLATE',
            'payment_reference' => 'REF-TEST-STILLLATE',
            'payment_due_date' => '2026-01-14',
            'early_payment_start_date' => '2026-01-01',
            'early_payment_end_date' => '2026-01-14',
            'credit_limit_snapshot' => 20000,
            'available_credit_snapshot' => 5000,
            'total_payment' => 4600.00,
            'total_commission' => 0.00,
            'total_late_fees' => 100.00,
            'total_amount_due' => 4700.00,
            'status' => CutoffRelationStatus::VENCIDA,
            'generated_at' => now(),
        ]);
        \App\Models\CutoffRelationItem::query()->create([
            'cutoff_relation_id' => $relation->id,
            'voucher_id' => $voucher->id,
            'customer_id' => $voucher->customer_id,
            'product_name_snapshot' => 'Producto',
            'payments_made' => 0,
            'total_payments' => 2,
            'is_late_payment' => true,
            'installment_number' => 1,
            'accumulated_late_installments' => 1,
            'commission_amount' => 0.00,
            'payment_amount' => 4600.00,
            'late_fee_amount' => 100.00,
            'line_total_amount' => 4700.00,
        ]);

        // El deposito real llego el 20 de enero -- ya fuera de la ventana
        // "a tiempo" (1-14 de enero): el atraso fue real.
        $transaction = BankTransaction::query()->create([
            'reference' => 'REF-EXCEL-TARDE',
            'transaction_date' => '2026-01-20',
            'amount' => 4700.00,
            'transaction_type' => 'DEPOSITO',
        ]);

        $cashier = User::factory()->create();
        reconciliationSignIn($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/reconciliations/bank-transactions/{$transaction->id}/manual-match", [
            'cutoff_relation_id' => $relation->id,
        ])->assertCreated()->assertJsonPath('data.is_retroactive_correction', true);

        $branchManager = User::factory()->create();
        reconciliationSignIn($branchManager, 'branch_manager', $branch);

        $this->postJson('/api/v1/reconciliations/1/verify')
            ->assertOk()
            ->assertJsonPath('data.status', 'CONCILIADA');

        // La multa se queda: el pago si llego tarde de verdad.
        $this->assertDatabaseHas('cutoff_relations', [
            'id' => $relation->id,
            'status' => 'PAGADA',
            'total_late_fees' => 100.00,
            'total_amount_due' => 4700.00,
        ]);

        $this->assertDatabaseHas('point_movements', [
            'distributor_id' => $distributor->id,
            'transaction_type' => 'GANADO_PUNTUAL',
        ]);
    });

    it('corrects the awarded points without re-advancing the voucher or releasing credit twice when the relation was already settled', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'credit_limit' => 20000,
            'available_credit' => 12000,
            'current_points' => 0,
        ]);

        $voucher = \App\Models\Voucher::factory()->create([
            'branch_id' => $branch->id,
            'distributor_id' => $distributor->id,
            'status' => \App\Enums\VoucherStatus::ACTIVO,
            'amount' => 8000.00,
            'distributor_profit_amount' => 400.00,
            'total_debt_amount' => 9200.00,
            'fortnightly_payment_amount' => 4600.00,
            'total_fortnights' => 2,
            'payments_made' => 1,
            'current_balance' => 4800.00,
        ]);

        $cutoff = \App\Models\Cutoff::factory()->create(['branch_id' => $branch->id]);
        $relation = CutoffRelation::query()->create([
            'cutoff_id' => $cutoff->id,
            'distributor_id' => $distributor->id,
            'relation_number' => 'REL-TEST-ALREADYSETTLED',
            'payment_reference' => 'REF-TEST-ALREADYSETTLED',
            'payment_due_date' => '2026-01-14',
            'early_payment_start_date' => '2026-01-01',
            'early_payment_end_date' => '2026-01-14',
            'credit_limit_snapshot' => 20000,
            'available_credit_snapshot' => 12000,
            'total_payment' => 4600.00,
            'total_commission' => 0.00,
            'total_late_fees' => 100.00,
            'total_amount_due' => 4700.00,
            // Ya se concilio (con la multa incluida) y ya se liquido: el vale
            // ya avanzo su quincena y el credito ya se libero.
            'status' => CutoffRelationStatus::PAGADA,
            'generated_at' => now(),
        ]);
        \App\Models\CutoffRelationItem::query()->create([
            'cutoff_relation_id' => $relation->id,
            'voucher_id' => $voucher->id,
            'customer_id' => $voucher->customer_id,
            'product_name_snapshot' => 'Producto',
            'payments_made' => 0,
            'total_payments' => 2,
            'is_late_payment' => true,
            'installment_number' => 1,
            'accumulated_late_installments' => 1,
            'commission_amount' => 0.00,
            'payment_amount' => 4600.00,
            'late_fee_amount' => 100.00,
            'line_total_amount' => 4700.00,
        ]);

        // El movimiento de puntos que ya se otorgo con el -20% al liquidarse:
        // floor(4600/1200)*3 = 9, con 20% de penalizacion = 7.
        \App\Models\PointMovement::query()->create([
            'distributor_id' => $distributor->id,
            'cutoff_id' => $cutoff->id,
            'transaction_type' => 'GANADO_PUNTUAL',
            'points' => 7,
            'point_value_snapshot' => 2.00,
            'reason' => 'Corte conciliado fuera de tiempo (-20% de puntos).',
            'transaction_date' => now(),
        ]);
        $distributor->increment('current_points', 7);

        $transaction = BankTransaction::query()->create([
            'reference' => 'REF-EXCEL-YAPAGADO',
            'transaction_date' => '2026-01-10',
            'amount' => 4700.00,
            'transaction_type' => 'DEPOSITO',
        ]);

        $cashier = User::factory()->create();
        reconciliationSignIn($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/reconciliations/bank-transactions/{$transaction->id}/manual-match", [
            'cutoff_relation_id' => $relation->id,
        ])->assertCreated()->assertJsonPath('data.is_retroactive_correction', true);

        $branchManager = User::factory()->create();
        reconciliationSignIn($branchManager, 'branch_manager', $branch);

        $this->postJson('/api/v1/reconciliations/1/verify')->assertOk();

        $this->assertDatabaseHas('cutoff_relations', [
            'id' => $relation->id,
            'status' => 'PAGADA',
            'total_late_fees' => 0.00,
        ]);

        // El vale y el credito NO se vuelven a tocar (ya se habian liquidado):
        // payments_made sigue en 1, no en 2, y el credito no se libera de
        // nuevo.
        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'payments_made' => 1,
            'current_balance' => 4800.00,
        ]);
        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'available_credit' => 12000.00,
        ]);

        // Solo se corrige la diferencia de puntos: 9 (completos) - 7 (ya
        // otorgados) = 2, via un movimiento de ajuste nuevo -- el original
        // (7, GANADO_PUNTUAL) no se toca.
        $this->assertDatabaseHas('point_movements', [
            'distributor_id' => $distributor->id,
            'transaction_type' => 'AJUSTE_MANUAL',
            'points' => 2,
        ]);
        $this->assertDatabaseHas('point_movements', [
            'distributor_id' => $distributor->id,
            'transaction_type' => 'GANADO_PUNTUAL',
            'points' => 7,
        ]);
        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'current_points' => 9.00,
        ]);
    });

    it('does not Forbidden a manual match just because the transaction id happens to differ from the user branch id (legacy transaction without branch_id)', function (): void {
        // Reproduce el bug reportado: una BankTransaction "legacy" (sin
        // branch_id, como las importadas antes de este fix, o creadas a mano
        // como en las pruebas de arriba) hacía que EnsureBusinessAbility
        // usara el ID NUMÉRICO PROPIO de la transacción como si fuera un
        // branch id. Esta sucursal "de relleno" fuerza a que el id real de la
        // sucursal del usuario (2) no coincida con el id de la transacción
        // (1), para que el escenario reportado (transacción #14 vs. una
        // sucursal con otro id) quede cubierto sin depender de que ambos IDs
        // coincidan por casualidad.
        Branch::factory()->create();
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'available_credit' => 10000,
        ]);
        $relation = reconciliationOpenRelation($branch, $distributor);

        $transaction = BankTransaction::query()->create([
            'reference' => 'REF-LEGACY',
            'transaction_date' => now()->subDay()->toDateString(),
            'amount' => 2599.00,
            'transaction_type' => 'DEPOSITO',
        ]);

        expect($transaction->branch_id)->toBeNull();
        expect($branch->id)->not->toBe($transaction->id);

        $cashier = User::factory()->create();
        reconciliationSignIn($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/reconciliations/bank-transactions/{$transaction->id}/manual-match", [
            'cutoff_relation_id' => $relation->id,
        ])->assertCreated()
            ->assertJsonPath('data.status', 'PENDIENTE_VERIFICACION');
    });

    it('still enforces branch scoping for manual match when the bank transaction does have a branch_id', function (): void {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $distributor = Distributor::factory()->create([
            'branch_id' => $branchA->id,
            'available_credit' => 10000,
        ]);
        $relation = reconciliationOpenRelation($branchA, $distributor);

        $transaction = BankTransaction::query()->create([
            'branch_id' => $branchA->id,
            'reference' => 'REF-SCOPED',
            'transaction_date' => now()->subDay()->toDateString(),
            'amount' => 2599.00,
            'transaction_type' => 'DEPOSITO',
        ]);

        $cashierOtherBranch = User::factory()->create();
        reconciliationSignIn($cashierOtherBranch, 'cashier', $branchB);

        $this->postJson("/api/v1/reconciliations/bank-transactions/{$transaction->id}/manual-match", [
            'cutoff_relation_id' => $relation->id,
        ])->assertForbidden();

        $cashierSameBranch = User::factory()->create();
        reconciliationSignIn($cashierSameBranch, 'cashier', $branchA);

        $this->postJson("/api/v1/reconciliations/bank-transactions/{$transaction->id}/manual-match", [
            'cutoff_relation_id' => $relation->id,
        ])->assertCreated();
    });
});