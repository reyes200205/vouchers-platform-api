<?php

declare(strict_types=1);

use App\Enums\CutoffRelationStatus;
use App\Models\BankTransaction;
use App\Models\Branch;
use App\Models\Cutoff;
use App\Models\CutoffRelation;
use App\Models\Distributor;
use App\Models\DistributorCategory;
use App\Models\PointRedemption;
use App\Models\Role;
use App\Models\User;
use App\Services\Reconciliations\AutoMatchDepositsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function pointSignInAsDistributor(User $user, Distributor $distributor): void
{
    $role = Role::query()->firstOrCreate(['code' => 'distributor'], ['name' => 'distributor']);
    $user->update(['person_id' => $distributor->person_id]);
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

/**
 * Crea una relación de corte GENERADA lista para conciliar (como si ya la
 * hubiera generado GenerateCutoffService), con el monto exacto que se le va a
 * "depositar" en el test.
 */
function pointRelation(Branch $branch, Distributor $distributor, float $totalPayment, float $totalLateFees = 0.00): CutoffRelation
{
    return CutoffRelation::query()->create([
        'cutoff_id' => Cutoff::factory()->create(['branch_id' => $branch->id])->id,
        'distributor_id' => $distributor->id,
        'relation_number' => 'REL-PTS-' . fake()->unique()->numberBetween(1000, 9999),
        'payment_reference' => 'REF-PTS-' . fake()->unique()->numberBetween(1000, 9999),
        'payment_due_date' => now()->addDays(15)->toDateString(),
        'early_payment_start_date' => now()->toDateString(),
        'early_payment_end_date' => now()->addDays(14)->toDateString(),
        'total_payment' => $totalPayment,
        'total_commission' => 0.00,
        'total_late_fees' => $totalLateFees,
        'total_amount_due' => round($totalPayment + $totalLateFees, 2),
        'status' => CutoffRelationStatus::GENERADA,
        'generated_at' => now(),
    ]);
}

describe('Points', function (): void {
    it('awards the full formula points for an on-time (anticipado) settlement — no extra bonus on top', function (): void {
        // Los puntos son de la DISTRIBUIDORA, no del cliente: se otorgan cuando
        // su CutoffRelation queda conciliada (PAGADA), no al registrarse un pago
        // individual (eso ya no existe como fuente de verdad — ver
        // SettleCutoffRelationService). La fórmula (total/1200 piso *
        // multiplicador) YA ES el cálculo para "pagos anticipados" — no hay un
        // porcentaje de bono aparte encima de eso.
        $branch = Branch::factory()->create();
        $category = DistributorCategory::factory()->create(['points_per_1200' => 1]);
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'current_points' => 0,
        ]);

        $relation = pointRelation($branch, $distributor, 3600.00);

        BankTransaction::query()->create([
            'reference' => $relation->payment_reference,
            'transaction_date' => now()->toDateString(),
            'amount' => 3600.00,
            'transaction_type' => 'DEPOSITO',
        ]);

        $cashier = User::factory()->create();
        pointSignInBusinessRole($cashier, 'cashier', $branch);

        app(AutoMatchDepositsService::class)->execute($cashier);

        $this->assertDatabaseHas('cutoff_relations', [
            'id' => $relation->id,
            'status' => 'PAGADA',
        ]);

        // basePoints = floor(3600 / 1200) * 1 = 3. Sin bono extra: los puntos
        // otorgados son exactamente la fórmula.
        $this->assertDatabaseHas('point_movements', [
            'distributor_id' => $distributor->id,
            'transaction_type' => 'GANADO_ANTICIPADO',
            'points' => 3,
        ]);

        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'current_points' => 3.00,
        ]);
    });

    it('applies the configured late-payment penalty to the points when the relation was ever marked overdue', function (): void {
        $branch = Branch::factory()->create();
        $category = DistributorCategory::factory()->create(['points_per_1200' => 1]);
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'current_points' => 0,
        ]);

        // total_late_fees > 0 es la señal de que esta relación llegó a
        // vencerse (MarkOverdueRelationsService le aplica la multa ahí); eso es
        // lo único que SettleCutoffRelationService usa para decidir el -20%,
        // para no repetir lógica de fechas en más de un lugar.
        $relation = pointRelation($branch, $distributor, 3600.00, 150.00);

        BankTransaction::query()->create([
            'reference' => $relation->payment_reference,
            'transaction_date' => now()->toDateString(),
            'amount' => 3750.00,
            'transaction_type' => 'DEPOSITO',
        ]);

        $cashier = User::factory()->create();
        pointSignInBusinessRole($cashier, 'cashier', $branch);

        app(AutoMatchDepositsService::class)->execute($cashier);

        $this->assertDatabaseHas('cutoff_relations', [
            'id' => $relation->id,
            'status' => 'PAGADA',
        ]);

        // basePoints = floor(3600 / 1200) * 1 = 3; -20% (default de
        // point_settings.late_penalty_percentage) => floor(3 * 0.80) = 2.
        $this->assertDatabaseHas('point_movements', [
            'distributor_id' => $distributor->id,
            'transaction_type' => 'GANADO_PUNTUAL',
            'points' => 2,
        ]);

        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'current_points' => 2.00,
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