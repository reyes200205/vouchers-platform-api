<?php

declare(strict_types=1);

use App\Enums\CutoffRelationStatus;
use App\Models\Branch;
use App\Models\Cutoff;
use App\Models\CutoffRelation;
use App\Models\CutoffRelationItem;
use App\Models\Customer;
use App\Models\Distributor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function relationSignInDistributor(User $user, Distributor $distributor): void
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

function relationForDistributor(Branch $branch, Distributor $distributor): CutoffRelation
{
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);

    $relation = CutoffRelation::query()->create([
        'cutoff_id' => Cutoff::factory()->create(['branch_id' => $branch->id])->id,
        'distributor_id' => $distributor->id,
        'relation_number' => 'REL-TEST-' . fake()->unique()->numberBetween(1000, 9999),
        'payment_reference' => 'REF-TEST' . fake()->unique()->numberBetween(1000, 9999),
        'payment_due_date' => now()->addDays(10)->toDateString(),
        'credit_limit_snapshot' => 20000,
        'available_credit_snapshot' => 20000,
        'total_payment' => 2825.00,
        'total_commission' => 150.00,
        'total_late_fees' => 0.00,
        'total_amount_due' => 2675.00,
        'status' => CutoffRelationStatus::GENERADA,
        'generated_at' => now(),
    ]);

    CutoffRelationItem::query()->create([
        'cutoff_relation_id' => $relation->id,
        'voucher_id' => \App\Models\Voucher::factory()->create([
            'branch_id' => $branch->id,
            'distributor_id' => $distributor->id,
            'customer_id' => $customer->id,
        ])->id,
        'customer_id' => $customer->id,
        'product_name_snapshot' => 'Producto de prueba',
        'payments_made' => 2,
        'total_payments' => 8,
        'is_late_payment' => false,
        'installment_number' => 3,
        'accumulated_late_installments' => 0,
        'commission_amount' => 150.00,
        'payment_amount' => 2825.00,
        'late_fee_amount' => 0.00,
        'line_total_amount' => 2675.00,
    ]);

    return $relation;
}

describe('Distributor account statement (own relations only)', function (): void {
    it('lists only the authenticated distributor own cutoff relations, with customer names on each item', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $otherDistributor = Distributor::factory()->create(['branch_id' => $branch->id]);

        $ownRelation = relationForDistributor($branch, $distributor);
        relationForDistributor($branch, $otherDistributor);

        $user = User::factory()->create();
        relationSignInDistributor($user, $distributor);

        $response = $this->getJson('/api/v1/distributor/relations')
            ->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id');
        expect($ids)->toHaveCount(1)
            ->and($ids->first())->toBe($ownRelation->id);

        $item = $response->json('data.data.0.items.0');
        expect($item['customer']['person'])->not->toBeNull();
    });

    it('refuses to show a cutoff relation that belongs to a different distributor', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $otherDistributor = Distributor::factory()->create(['branch_id' => $branch->id]);

        $otherRelation = relationForDistributor($branch, $otherDistributor);

        $user = User::factory()->create();
        relationSignInDistributor($user, $distributor);

        $this->getJson("/api/v1/distributor/relations/{$otherRelation->id}")
            ->assertStatus(404);
    });

    it('shows the distributor their own relation with items', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $relation = relationForDistributor($branch, $distributor);

        $user = User::factory()->create();
        relationSignInDistributor($user, $distributor);

        $this->getJson("/api/v1/distributor/relations/{$relation->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $relation->id)
            ->assertJsonPath('data.status', 'GENERADA')
            ->assertJsonPath('data.total_amount_due', '2675.00');
    });

    it('forbids a non-distributor role from using the distributor statements endpoint', function (): void {
        $branch = Branch::factory()->create();
        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate(['code' => 'cashier'], ['name' => 'cashier']);
        $user->businessRoles()->attach($role, [
            'branch_id' => $branch->id,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/distributor/relations')->assertForbidden();
    });
});
