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

    it('generates a folio as soon as the distributor requests a redemption', function (): void {
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
            ->assertJsonPath('data.folio', fn ($folio) => is_string($folio) && str_starts_with($folio, 'CANJE-'));
    });

    it('lets a cashier of the same branch pay out a redemption by folio, without manager approval', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'current_points' => 500,
        ]);

        $distributorUser = User::factory()->create();
        pointSignInAsDistributor($distributorUser, $distributor);

        $folio = $this->postJson("/api/v1/distributors/{$distributor->id}/points/redeem", [
            'points' => '200.00',
        ])->assertCreated()->json('data.folio');

        $cashier = User::factory()->create();
        pointSignInBusinessRole($cashier, 'cashier', $branch);

        $this->getJson("/api/v1/point-redemptions/lookup/{$folio}")
            ->assertOk()
            ->assertJsonPath('data.status', 'PENDIENTE')
            ->assertJsonPath('data.amount_mxn', '400.00');

        $this->postJson("/api/v1/point-redemptions/lookup/{$folio}/payout")
            ->assertOk()
            ->assertJsonPath('data.status', 'APROBADO')
            ->assertJsonPath('data.decided_by_user_id', $cashier->id);

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

    it('forbids a cashier from another branch from paying out a redemption', function (): void {
        $branch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'current_points' => 500,
        ]);

        $distributorUser = User::factory()->create();
        pointSignInAsDistributor($distributorUser, $distributor);

        $folio = $this->postJson("/api/v1/distributors/{$distributor->id}/points/redeem", [
            'points' => '200.00',
        ])->assertCreated()->json('data.folio');

        $cashier = User::factory()->create();
        pointSignInBusinessRole($cashier, 'cashier', $otherBranch);

        $this->postJson("/api/v1/point-redemptions/lookup/{$folio}/payout")->assertForbidden();

        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'current_points' => 500.00,
        ]);
    });

    it('rejects paying out an unknown folio', function (): void {
        $branch = Branch::factory()->create();
        $cashier = User::factory()->create();
        pointSignInBusinessRole($cashier, 'cashier', $branch);

        $this->postJson('/api/v1/point-redemptions/lookup/CANJE-00000000/payout')->assertNotFound();
    });

    it('lets a cashier list paid redemptions of their branch, filtered by distributor number', function (): void {
        $branch = Branch::factory()->create();
        $distributorA = Distributor::factory()->create(['branch_id' => $branch->id, 'current_points' => 500, 'distributor_number' => 'DIST-AAA111']);
        $distributorB = Distributor::factory()->create(['branch_id' => $branch->id, 'current_points' => 500, 'distributor_number' => 'DIST-BBB222']);

        $distributorAUser = User::factory()->create();
        pointSignInAsDistributor($distributorAUser, $distributorA);
        $folioA = $this->postJson("/api/v1/distributors/{$distributorA->id}/points/redeem", ['points' => '100.00'])->json('data.folio');

        $distributorBUser = User::factory()->create();
        pointSignInAsDistributor($distributorBUser, $distributorB);
        $folioB = $this->postJson("/api/v1/distributors/{$distributorB->id}/points/redeem", ['points' => '150.00'])->json('data.folio');

        $cashier = User::factory()->create();
        pointSignInBusinessRole($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/point-redemptions/lookup/{$folioA}/payout")->assertOk();
        $this->postJson("/api/v1/point-redemptions/lookup/{$folioB}/payout")->assertOk();

        $this->getJson('/api/v1/point-redemptions?status=APROBADO&distributor_number=AAA')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.distributor.distributor_number', 'DIST-AAA111');
    });

    it('rejects paying out a redemption twice', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'current_points' => 500,
        ]);

        $distributorUser = User::factory()->create();
        pointSignInAsDistributor($distributorUser, $distributor);

        $folio = $this->postJson("/api/v1/distributors/{$distributor->id}/points/redeem", [
            'points' => '200.00',
        ])->assertCreated()->json('data.folio');

        $cashier = User::factory()->create();
        pointSignInBusinessRole($cashier, 'cashier', $branch);

        $this->postJson("/api/v1/point-redemptions/lookup/{$folio}/payout")->assertOk();
        $this->postJson("/api/v1/point-redemptions/lookup/{$folio}/payout")->assertStatus(422);
    });
});