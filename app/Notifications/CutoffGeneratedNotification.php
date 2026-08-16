<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Cutoff;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class CutoffGeneratedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Cutoff $cutoff,
        public readonly int $relationCount,
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
            'type' => 'cutoff_generated',
            'cutoff_id' => $this->cutoff->id,
            'relation_count' => $this->relationCount,
            'message' => 'Se generó un nuevo corte (' . $this->cutoff->status?->value . ') con '
                . $this->relationCount . ' relación(es) pendiente(s) de pago.',
        ];
    }
}