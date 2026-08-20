<?php

declare(strict_types=1);

use App\Enums\CutoffRelationStatus;
use App\Enums\DistributorStatus;
use App\Enums\VoucherStatus;
use App\Models\Branch;
use App\Models\Cutoff;
use App\Models\CutoffRelation;
use App\Models\CutoffRelationItem;
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
    $user->businessRoles()->attach($role, [
        'branch_id' => $branch->id,
        'assigned_at' => now(),
        'is_primary' => true,
    ]);
    Sanctum::actingAs($user);
}

describe('Scheduler and notifications', function (): void {
    it('marks the cutoff relation as VENCIDA, applies the late fee, drops the commission and flags the voucher as MOROSO when the due date passes', function (): void {
        $branch = Branch::factory()->create();
        $distributor = Distributor::factory()->create(['branch_id' => $branch->id]);
        $voucher = Voucher::factory()->create([
            'branch_id' => $branch->id,
            'distributor_id' => $distributor->id,
            'status' => VoucherStatus::ACTIVO,
            'payment_due_date' => now()->subDays(2)->toDateString(),
            'current_balance' => 5000.00,
            'fortnightly_payment_amount' => 2500.00,
            'late_fee_amount_snapshot' => 150.00,
        ]);
        $onTimeVoucher = Voucher::factory()->create([
            'branch_id' => $branch->id,
            'distributor_id' => $distributor->id,
            'status' => VoucherStatus::ACTIVO,
            'payment_due_date' => now()->addDays(10)->toDateString(),
            'current_balance' => 5000.00,
        ]);

        $cutoff = Cutoff::factory()->create(['branch_id' => $branch->id]);
        $relation = CutoffRelation::query()->create([
            'cutoff_id' => $cutoff->id,
            'distributor_id' => $distributor->id,
            'relation_number' => 'REL-OVERDUE',
            'payment_reference' => 'REF-OVERDUE',
            'payment_due_date' => now()->subDays(1)->toDateString(),
            'total_payment' => 2500.00,
            'total_commission' => 100.00,
            'total_amount_due' => 2400.00,
            'status' => CutoffRelationStatus::GENERADA,
            'generated_at' => now()->subDays(15),
        ]);
        $item = CutoffRelationItem::query()->create([
            'cutoff_relation_id' => $relation->id,
            'voucher_id' => $voucher->id,
            'customer_id' => $voucher->customer_id,
            'product_name_snapshot' => 'Producto',
            'payments_made' => 0,
            'total_payments' => $voucher->total_fortnights,
            'is_late_payment' => false,
            'installment_number' => 1,
            'accumulated_late_installments' => 0,
            'commission_amount' => 100.00,
            'payment_amount' => 2500.00,
            'late_fee_amount' => 0.00,
            'line_total_amount' => 2400.00,
        ]);

        Artisan::call('cutoffs:mark-overdue');

        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'status' => 'MOROSO',
        ]);

        $this->assertDatabaseHas('vouchers', [
            'id' => $onTimeVoucher->id,
            'status' => 'ACTIVO',
        ]);

        $relation->refresh();
        $item->refresh();

        expect($relation->status)->toBe(CutoffRelationStatus::VENCIDA)
            ->and((float) $relation->total_late_fees)->toBe(150.00)
            ->and((float) $relation->total_commission)->toBe(0.00)
            ->and((float) $item->late_fee_amount)->toBe(150.00)
            ->and((float) $item->commission_amount)->toBe(0.00)
            ->and($item->is_late_payment)->toBeTrue();
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
            ->toContain('vouchers:send-reminders');
    });
});