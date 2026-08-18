<?php

declare(strict_types=1);

use App\Models\BankTransaction;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Distributor;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function cashierFlowSignIn(User $user, string $roleCode, Branch $branch): void
{
    $role = Role::query()->firstOrCreate(['code' => $roleCode], ['name' => $roleCode]);
    $user->businessRoles()->attach($role, [
        'branch_id' => $branch->id,
        'assigned_at' => now(),
        'is_primary' => true,
    ]);
    Sanctum::actingAs($user);
}

describe('Cashier flow support', function (): void {
    it('uploads customer verification photos as a cashier', function (): void {
        Storage::fake('public');

        $branch = Branch::factory()->create();
        $customer = Customer::factory()->create(['branch_id' => $branch->id]);

        $cashier = User::factory()->create();
        cashierFlowSignIn($cashier, 'cashier', $branch);

        $response = $this->postJson("/api/v1/customers/{$customer->id}/verification-photos", [
            'type' => 'id_front_photo',
            'photo' => UploadedFile::fake()->image('ine-front.jpg'),
        ])->assertCreated()
            ->assertJsonPath('data.type', 'id_front_photo')
            ->assertJsonStructure(['data' => ['type', 'url']]);

        $files = Storage::disk('public')->files('verifications/customers/' . $customer->id);
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.jpg', $files[0]);
    });

    it('forbids photo uploads for a cashier of another branch', function (): void {
        $branch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $customer = Customer::factory()->create(['branch_id' => $branch->id]);

        $cashier = User::factory()->create();
        cashierFlowSignIn($cashier, 'cashier', $otherBranch);

        $this->postJson("/api/v1/customers/{$customer->id}/verification-photos", [
            'type' => 'id_selfie_photo',
            'photo' => UploadedFile::fake()->image('selfie.jpg'),
        ])->assertForbidden();
    });

    it('lets a branch manager verify pending reconciliations of their branch only', function (): void {
        $branch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'available_credit' => 10000,
        ]);
        $relation = reconciliationOpenRelation($branch, $distributor);

        $transaction = BankTransaction::query()->create([
            'reference' => 'REF-BM-VERIFY',
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

        $otherManager = User::factory()->create();
        reconciliationSignIn($otherManager, 'branch_manager', $otherBranch);
        $this->postJson('/api/v1/reconciliations/1/verify')->assertForbidden();

        $branchManager = User::factory()->create();
        reconciliationSignIn($branchManager, 'branch_manager', $branch);
        $this->postJson('/api/v1/reconciliations/1/verify')
            ->assertOk()
            ->assertJsonPath('data.status', 'CONCILIADA');
    });

    it('seeds the demo cashier flow', function (): void {
        $this->seed(\Database\Seeders\AlessandroDemoSeeder::class);

        $cashier = User::query()->where('username', 'cajera')->firstOrFail();
        $this->assertTrue($cashier->businessRoles->contains(fn ($role) => $role->code === 'cashier'));

        $this->assertDatabaseCount('customer_change_requests', 2);
        $this->assertDatabaseCount('vouchers', 2 + 36);
        $this->assertDatabaseHas('vouchers', ['status' => 'APROBADO']);
    });
});