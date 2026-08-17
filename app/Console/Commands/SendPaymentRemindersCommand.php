<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\VoucherStatus;
use App\Models\User;
use App\Models\Voucher;
use App\Notifications\PaymentDueReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

final class SendPaymentRemindersCommand extends Command
{
    protected $signature = 'vouchers:send-reminders';

    protected $description = 'Envía recordatorios de pago a las distribuidoras con vales por vencer en 3 días.';

    public function handle(): int
    {
        $target = Carbon::today()->addDays(3)->toDateString();

        $vouchers = Voucher::query()
            ->whereIn('status', [VoucherStatus::ACTIVO, VoucherStatus::PAGO_PARCIAL])
            ->where('current_balance', '>', 0)
            ->whereDate('payment_due_date', '=', $target)
            ->with('distributor')
            ->get();

        $sent = 0;

        foreach ($vouchers as $voucher) {
            $users = User::query()
                ->where('person_id', $voucher->distributor?->person_id)
                ->get();

            Notification::send($users, new PaymentDueReminderNotification($voucher));
            $sent++;
        }

        $this->info("Recordatorios enviados: {$sent}");

        return self::SUCCESS;
    }
}