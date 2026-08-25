<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\CustomerTransferRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class CustomerTransferAwaitingCoordinatorNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly CustomerTransferRequest $transferRequest,
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
            'type' => 'customer_transfer_awaiting_coordinator',
            'customer_transfer_request_id' => $this->transferRequest->id,
            'customer_id' => $this->transferRequest->customer_id,
            'source_distributor_id' => $this->transferRequest->source_distributor_id,
            'destination_distributor_id' => $this->transferRequest->destination_distributor_id,
            'message' => 'La distribuidora destino aceptó una transferencia de cliente. Está pendiente de tu autorización.',
        ];
    }
}
