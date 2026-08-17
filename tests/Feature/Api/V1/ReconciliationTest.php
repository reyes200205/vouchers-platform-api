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
    $user->update(['role_id' => $role->id, 'branch_id' => $branch->id]);
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

        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'available_credit' => 12599.00,
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

        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'available_credit' => 11500.00,
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

        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'available_credit' => 12599.00,
        ]);
    });
});