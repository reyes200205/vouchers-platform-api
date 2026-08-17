<?php

declare(strict_types=1);

use App\Enums\CutoffRelationStatus;
use App\Enums\DistributorStatus;
use App\Enums\PaymentMethod;
use App\Enums\VoucherStatus;
use App\Models\Branch;
use App\Models\CustomerPayment;
use App\Models\Cutoff;
use App\Models\CutoffRelation;
use App\Models\Distributor;
use App\Models\Role;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function schedulerSignIn(User $user, string $roleCode, Branch $branch): void
{
    $role = Role::query()->firstOrCreate(['code' => $roleCode], ['name' => $roleCode]);
    $user->update(['role_id' => $role->id, 'branch_id' => $branch->id]);
    Sanctum::actingAs($user);
}

describe('Scheduler and notifications', function (): void {
    it('marks vouchers as MOROSO when the due date passes', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $voucher = Voucher::factory()->create([
            'branch_id' => $branch->id,
            'distributor_id' => $distributor->id,
            'status' => VoucherStatus::ACTIVO,
            'payment_due_date' => now()->subDays(2)->toDateString(),
            'current_balance' => 5000.00,
        ]);
        Voucher::factory()->create([
            'branch_id' => $branch->id,
            'distributor_id' => $distributor->id,
            'status' => VoucherStatus::ACTIVO,
            'payment_due_date' => now()->addDays(10)->toDateString(),
            'current_balance' => 5000.00,
        ]);

        Artisan::call('vouchers:mark-overdue');

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'status' => 'MOROSO',
        ]);

        expect(Voucher::query()->where('status', VoucherStatus::MOROSO)->count())->toBe(1);
    });

    it('blocks a distributor after 3 consecutive overdue cutoffs and notifies', function (): void {
        $branch = Branch::factory()->create();
        $coordinator = User::factory()->create();
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'coordinator_user_id' => $coordinator->id,
            'status' => DistributorStatus::ACTIVA,
            'can_issue_vouchers' => true,
        ]);

        for ($i = 0; $i < 3; $i++) {
            $cutoff = Cutoff::factory()->create([
                'branch_id' => $branch->id,
                'scheduled_at' => now()->subDays(20 - $i * 16),
            ]);

            CutoffRelation::query()->create([
                'cutoff_id' => $cutoff->id,
                'distributor_id' => $distributor->id,
                'relation_number' => 'REL-' . fake()->unique()->numberBetween(1000, 9999),
                'payment_reference' => 'REF-' . fake()->unique()->numberBetween(1000, 9999),
                'payment_due_date' => now()->subDays(5)->toDateString(),
                'total_amount_due' => 2599.00,
                'status' => CutoffRelationStatus::VENCIDA,
                'generated_at' => now()->subDays(20 - $i * 16),
            ]);
        }

        Artisan::call('cutoffs:mark-overdue');

        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'status' => 'BLOQUEADA',
            'can_issue_vouchers' => 0,
        ]);

        $this->assertDatabaseHas('notifications', [
            'type' => \App\Notifications\DistributorBlockedNotification::class,
        ]);
    });

    it('does not block a distributor with fewer than 3 overdue cutoffs', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create([
            'branch_id' => $branch->id,
            'status' => DistributorStatus::ACTIVA,
            'can_issue_vouchers' => true,
        ]);

        $cutoff = Cutoff::factory()->create(['branch_id' => $branch->id]);
        CutoffRelation::query()->create([
            'cutoff_id' => $cutoff->id,
            'distributor_id' => $distributor->id,
            'relation_number' => 'REL-UNO',
            'payment_reference' => 'REF-UNO',
            'payment_due_date' => now()->subDays(5)->toDateString(),
            'total_amount_due' => 2599.00,
            'status' => CutoffRelationStatus::VENCIDA,
            'generated_at' => now(),
        ]);

        Artisan::call('cutoffs:mark-overdue');

        $this->assertDatabaseHas('distributors', [
            'id' => $distributor->id,
            'status' => 'ACTIVA',
            'can_issue_vouchers' => 1,
        ]);

        $this->assertDatabaseCount('notifications', 0);
    });

    it('sends payment reminders to distributor users with vouchers due in 3 days', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $user = User::factory()->create(['person_id' => $distributor->person_id]);

        Voucher::factory()->create([
            'branch_id' => $branch->id,
            'distributor_id' => $distributor->id,
            'status' => VoucherStatus::ACTIVO,
            'payment_due_date' => now()->addDays(3)->toDateString(),
            'current_balance' => 2825.00,
        ]);

        Artisan::call('vouchers:send-reminders');

        $this->assertDatabaseHas('notifications', [
            'type' => \App\Notifications\PaymentDueReminderNotification::class,
        ]);

        expect($user->notifications()->count())->toBe(1);
    });

    it('does not remind vouchers due outside the 3-day window', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);

        Voucher::factory()->create([
            'branch_id' => $branch->id,
            'distributor_id' => $distributor->id,
            'status' => VoucherStatus::ACTIVO,
            'payment_due_date' => now()->addDays(9)->toDateString(),
            'current_balance' => 2825.00,
        ]);

        Artisan::call('vouchers:send-reminders');

        $this->assertDatabaseCount('notifications', 0);
    });

    it('registers the scheduled commands for the daily run', function (): void {
        expect(Schedule::events())->not->toBeEmpty();

        $commands = array_map(
            fn ($event) => (string) $event->command,
            Schedule::events()
        );

        $all = implode(' ', $commands);

        expect($all)->toContain('cutoffs:generate')
            ->toContain('cutoffs:mark-overdue')
            ->toContain('vouchers:mark-overdue')
            ->toContain('vouchers:send-reminders');
    });
});