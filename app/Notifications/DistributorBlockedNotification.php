<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Distributor;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class DistributorBlockedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Distributor $distributor,
        public readonly int $consecutiveOverdueCutoffs,
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
            'type' => 'distributor_blocked',
            'distributor_id' => $this->distributor->id,
            'consecutive_overdue_cutoffs' => $this->consecutiveOverdueCutoffs,
            'message' => 'La distribuidora ' . ($this->distributor->business_name ?? $this->distributor->distributor_number)
                . ' fue bloqueada por acumular ' . $this->consecutiveOverdueCutoffs
                . ' cortes consecutivos sin pagar. Ya no puede emitir vales.',
        ];
    }
}