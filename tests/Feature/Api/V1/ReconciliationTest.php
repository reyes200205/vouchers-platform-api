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
});