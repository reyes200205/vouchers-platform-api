<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Distributor;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Aviso preventivo: la distribuidora lleva 2 cortes consecutivos sin pagar.
 * Todavía NO se le restringe nada (eso pasa hasta el tercero -- ver
 * DistributorDelinquentNotification / MarkOverdueRelationsCommand) -- es
 * solo una advertencia para que regularice antes de quedar MOROSA.
 */
final class DistributorOverdueNoticeNotification extends Notification
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
            'type' => 'distributor_overdue_notice',
            'distributor_id' => $this->distributor->id,
            'consecutive_overdue_cutoffs' => $this->consecutiveOverdueCutoffs,
            'message' => 'Aviso: la distribuidora ' . ($this->distributor->business_name ?? $this->distributor->distributor_number)
                . ' lleva ' . $this->consecutiveOverdueCutoffs
                . ' cortes consecutivos sin pagar. Si no regulariza en el siguiente corte, quedará marcada como MOROSA y no podrá emitir vales nuevos.',
        ];
    }
}
