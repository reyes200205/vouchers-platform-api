<?php

declare(strict_types=1);

use App\Enums\CutoffRelationStatus;
use App\Models\Application;
use App\Models\BankTransaction;
use App\Models\Branch;
use App\Models\CreditIncreaseRequest;
use App\Models\Cutoff;
use App\Models\CutoffRelation;
use App\Models\Distributor;
use App\Models\PointRedemption;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function inboxSignIn(User $user, string $roleCode = 'general_manager'): void
{
    $role = Role::query()->firstOrCreate(['code' => $roleCode], ['name' => $roleCode]);
    $user->businessRoles()->attach($role, [
        'branch_id' => null,
        'assigned_at' => now(),
        'is_primary' => true,
    ]);
    Sanctum::actingAs($user);

    // /general/inbox esta detras de vpn.restrict:general_manager,branch_manager
    // (ver routes/api/v1.php) -- sin simular una IP dentro del rango, cualquier
    // llamada de estos tests recibiria 403 sin importar el resto de la logica
    // que este archivo prueba. El comportamiento del middleware en si (bloquear
    // fuera de rango) ya se prueba aparte en VpnRestrictedApprovalsTest.php.
    config()->set('network.vpn_cidrs', ['10.0.0.0/8']);
    test()->withServerVariables(['REMOTE_ADDR' => '10.0.0.1']);
}

describe('General manager inbox', function (): void {
    it('lists pending applications, pre-authorized credit increases and pending redemptions', function (): void {
        $branch = Branch::factory()->create();
        $applicant = \App\Models\Person::factory()->create();
        $application = Application::query()->create([
            'applicant_person_id' => $applicant->id,
            'branch_id' => $branch->id,
            'status' => 'POSIBLE_DISTRIBUIDORA',
            'requested_credit_limit' => 60000,
            'initial_category_code' => 'COPPER',
        ]);

        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $requester = User::factory()->create();
        $credit = CreditIncreaseRequest::factory()->create([
            'distributor_id' => $distributor->id,
            'branch_id' => $branch->id,
            'requested_by_user_id' => $requester->id,
            'status' => 'PRE_AUTORIZADO',
            'pre_authorized_amount' => 25000,
        ]);

        $redemption = PointRedemption::factory()->create([
            'distributor_id' => $distributor->id,
            'branch_id' => $branch->id,
            'requested_by_user_id' => $requester->id,
            'status' => 'PENDIENTE',
        ]);

        $manager = User::factory()->create();
        inboxSignIn($manager);

        $this->getJson('/api/v1/general/inbox')
            ->assertOk()
            ->assertJsonPath('data.applications.total', 1)
            ->assertJsonPath('data.applications.items.0.id', $application->id)
            ->assertJsonPath('data.applications.items.0.type', 'application')
            ->assertJsonPath('data.credit_increases.total', 1)
            ->assertJsonPath('data.credit_increases.items.0.id', $credit->id)
            ->assertJsonPath('data.credit_increases.items.0.type', 'credit_increase')
            ->assertJsonPath('data.redemptions.total', 1)
            ->assertJsonPath('data.redemptions.items.0.id', $redemption->id)
            ->assertJsonPath('data.redemptions.items.0.type', 'redemption');
    });

    it('excludes non-pending records from each tab', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $requester = User::factory()->create();

        Application::query()->create([
            'applicant_person_id' => \App\Models\Person::factory()->create()->id,
            'branch_id' => $branch->id,
            'status' => 'APROBADA',
        ]);

        CreditIncreaseRequest::factory()->create([
            'distributor_id' => $distributor->id,
            'branch_id' => $branch->id,
            'requested_by_user_id' => $requester->id,
            'status' => 'PENDIENTE',
        ]);

        PointRedemption::factory()->create([
            'distributor_id' => $distributor->id,
            'branch_id' => $branch->id,
            'requested_by_user_id' => $requester->id,
            'status' => 'APROBADO',
        ]);

        $manager = User::factory()->create();
        inboxSignIn($manager);

        $this->getJson('/api/v1/general/inbox')
            ->assertOk()
            ->assertJsonPath('data.applications.total', 0)
            ->assertJsonPath('data.credit_increases.total', 0)
            ->assertJsonPath('data.redemptions.total', 0);
    });

    it('supports tab and branch_id filters', function (): void {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        Application::query()->create([
            'applicant_person_id' => \App\Models\Person::factory()->create()->id,
            'branch_id' => $branchA->id,
            'status' => 'POSIBLE_DISTRIBUIDORA',
        ]);
        Application::query()->create([
            'applicant_person_id' => \App\Models\Person::factory()->create()->id,
            'branch_id' => $branchB->id,
            'status' => 'POSIBLE_DISTRIBUIDORA',
        ]);

        $manager = User::factory()->create();
        inboxSignIn($manager);

        $this->getJson('/api/v1/general/inbox?tab=applications&branch_id='.$branchA->id)
            ->assertOk()
            ->assertJsonPath('data.applications.total', 1)
            ->assertJsonMissingPath('data.credit_increases')
            ->assertJsonMissingPath('data.redemptions');
    });

    it('restricts a branch manager to their own branches', function (): void {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();

        Application::query()->create([
            'applicant_person_id' => \App\Models\Person::factory()->create()->id,
            'branch_id' => $branchA->id,
            'status' => 'POSIBLE_DISTRIBUIDORA',
        ]);
        Application::query()->create([
            'applicant_person_id' => \App\Models\Person::factory()->create()->id,
            'branch_id' => $branchB->id,
            'status' => 'POSIBLE_DISTRIBUIDORA',
        ]);

        $manager = User::factory()->create();
        $role = Role::query()->firstOrCreate(['code' => 'branch_manager'], ['name' => 'branch_manager']);
        $manager->businessRoles()->attach($role, [
            'branch_id' => $branchA->id,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);
        Sanctum::actingAs($manager);
        config()->set('network.vpn_cidrs', ['10.0.0.0/8']);
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1']);

        $this->getJson('/api/v1/general/inbox')
            ->assertOk()
            ->assertJsonPath('data.applications.total', 1)
            ->assertJsonPath('data.applications.items.0.branch_id', $branchA->id);
    });

    it('ignores a branch_id query param outside what the branch manager is allowed to see', function (): void {
        // Antes, branch_id en el query pisaba por completo el filtro de
        // sucursales del usuario (activeBusinessBranchIds()): un gerente de
        // sucursal podía pedir la bandeja de OTRA sucursal con solo cambiar
        // el query param, sin que la ability lo evitara.
        $ownBranch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();

        Application::query()->create([
            'applicant_person_id' => \App\Models\Person::factory()->create()->id,
            'branch_id' => $otherBranch->id,
            'status' => 'POSIBLE_DISTRIBUIDORA',
        ]);

        $manager = User::factory()->create();
        $role = Role::query()->firstOrCreate(['code' => 'branch_manager'], ['name' => 'branch_manager']);
        $manager->businessRoles()->attach($role, [
            'branch_id' => $ownBranch->id,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);
        Sanctum::actingAs($manager);
        config()->set('network.vpn_cidrs', ['10.0.0.0/8']);
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1']);

        $this->getJson('/api/v1/general/inbox?tab=applications&branch_id='.$otherBranch->id)
            ->assertOk()
            ->assertJsonPath('data.applications.total', 0);
    });

    it('forbids a distributor from viewing the inbox', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);

        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate(['code' => 'distributor'], ['name' => 'distributor']);
        $user->businessRoles()->attach($role, [
            'branch_id' => $branch->id,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/general/inbox')->assertForbidden();
    });

    it('includes a branch-scoped reconciliations tab with the full distributor/relation/bank-transaction context', function (): void {
        // La conciliacion manual ya tenia su propia pantalla, pero esa
        // pantalla no pasaba por el mismo ocultamiento-sin-VPN que la
        // Bandeja de Aprobaciones. Esta pestana reutiliza el mismo
        // InboxController para que la segunda autorizacion de conciliaciones
        // quede cubierta por el mismo mecanismo (ver vpn.restrict en
        // reconciliations.verify/reject).
        $branch = Branch::factory()->create(['name' => 'Sucursal Inbox']);
        $otherBranch = Branch::factory()->create();

        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'available_credit' => 10000,
            'distributor_number' => 'DIST-INBOX-001',
        ]);

        $relation = CutoffRelation::query()->create([
            'cutoff_id' => Cutoff::factory()->create(['branch_id' => $branch->id])->id,
            'distributor_id' => $distributor->id,
            'relation_number' => 'REL-INBOX-0001',
            'payment_reference' => 'REF-INBOX-0001',
            'payment_due_date' => now()->addDays(10)->toDateString(),
            'credit_limit_snapshot' => 20000,
            'available_credit_snapshot' => 20000,
            'total_payment' => 2599.00,
            'total_commission' => 226.00,
            'total_late_fees' => 0.00,
            'total_amount_due' => 2599.00,
            'status' => CutoffRelationStatus::GENERADA,
            'generated_at' => now(),
        ]);

        $transaction = BankTransaction::query()->create([
            'reference' => 'REF-INBOX-TXN',
            'transaction_date' => now()->subDay()->toDateString(),
            'amount' => 2599.00,
            'transaction_type' => 'DEPOSITO',
        ]);

        $cashier = User::factory()->create();
        $cashierRole = Role::query()->firstOrCreate(['code' => 'cashier'], ['name' => 'cashier']);
        $cashier->businessRoles()->attach($cashierRole, [
            'branch_id' => $branch->id,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);
        Sanctum::actingAs($cashier);
        config()->set('network.vpn_cidrs', ['10.0.0.0/8']);
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1']);

        $this->postJson("/api/v1/reconciliations/bank-transactions/{$transaction->id}/manual-match", [
            'cutoff_relation_id' => $relation->id,
        ])->assertCreated();

        $branchManager = User::factory()->create();
        inboxSignIn($branchManager, 'branch_manager');
        // inboxSignIn() adjunta el rol sin sucursal (branch_id null); lo
        // volvemos a adjuntar con la sucursal correcta para que quede
        // limitado a ella.
        $branchManager->businessRoles()->updateExistingPivot(
            Role::query()->where('code', 'branch_manager')->value('id'),
            ['branch_id' => $branch->id]
        );

        $this->getJson('/api/v1/general/inbox?tab=reconciliations')
            ->assertOk()
            ->assertJsonPath('data.reconciliations.total', 1)
            ->assertJsonPath('data.reconciliations.items.0.type', 'reconciliation')
            ->assertJsonPath('data.reconciliations.items.0.distributor_payment.distributor.distributor_number', 'DIST-INBOX-001')
            ->assertJsonPath('data.reconciliations.items.0.distributor_payment.cutoff_relation.relation_number', 'REL-INBOX-0001')
            ->assertJsonPath('data.reconciliations.items.0.distributor_payment.cutoff_relation.cutoff.branch_name', 'Sucursal Inbox')
            ->assertJsonPath('data.reconciliations.items.0.bank_transaction.reference', 'REF-INBOX-TXN');

        // Un gerente de OTRA sucursal no debe ver esta conciliacion pendiente.
        $otherManager = User::factory()->create();
        $otherRole = Role::query()->where('code', 'branch_manager')->first();
        $otherManager->businessRoles()->attach($otherRole, [
            'branch_id' => $otherBranch->id,
            'assigned_at' => now(),
            'is_primary' => true,
        ]);
        Sanctum::actingAs($otherManager);
        config()->set('network.vpn_cidrs', ['10.0.0.0/8']);
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1']);

        $this->getJson('/api/v1/general/inbox?tab=reconciliations')
            ->assertOk()
            ->assertJsonPath('data.reconciliations.total', 0);
    });
});