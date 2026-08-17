<?php

declare(strict_types=1);

use App\Models\Application;
use App\Models\Branch;
use App\Models\CreditIncreaseRequest;
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

        $this->getJson('/api/v1/general/inbox')
            ->assertOk()
            ->assertJsonPath('data.applications.total', 1)
            ->assertJsonPath('data.applications.items.0.branch_id', $branchA->id);
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
});