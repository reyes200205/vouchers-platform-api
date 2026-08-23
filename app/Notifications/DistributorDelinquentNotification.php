<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Distributor;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * La distribuidora acumuló 3 (o más) cortes consecutivos sin pagar: queda
 * MOROSA y ya no puede emitir vales nuevos hasta regularizar. Antes de
 * llegar aquí ya recibió un aviso preventivo al segundo corte consecutivo
 * (ver DistributorOverdueNoticeNotification) -- reemplaza a la antigua
 * DistributorBlockedNotification (que marcaba BLOQUEADA directo al tercero,
 * sin aviso previo).
 */
final class DistributorDelinquentNotification extends Notification
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
            'type' => 'distributor_delinquent',
            'distributor_id' => $this->distributor->id,
            'consecutive_overdue_cutoffs' => $this->consecutiveOverdueCutoffs,
            'message' => 'La distribuidora ' . ($this->distributor->business_name ?? $this->distributor->distributor_number)
                . ' quedó MOROSA por acumular ' . $this->consecutiveOverdueCutoffs
                . ' cortes consecutivos sin pagar. Ya no puede emitir vales nuevos.',
        ];
    }
}
