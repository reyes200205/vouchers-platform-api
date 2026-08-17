<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Voucher;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class PaymentDueReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Voucher $voucher,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_due_reminder',
            'voucher_id' => $this->voucher->id,
            'voucher_number' => $this->voucher->voucher_number,
            'due_date' => $this->voucher->payment_due_date?->toDateString(),
            'current_balance' => $this->voucher->current_balance,
            'message' => 'El vale ' . $this->voucher->voucher_number . ' vence el '
                . $this->voucher->payment_due_date?->toDateString()
                . ' con un saldo pendiente de $' . number_format((float) $this->voucher->current_balance, 2) . '.',
        ];
    }
}