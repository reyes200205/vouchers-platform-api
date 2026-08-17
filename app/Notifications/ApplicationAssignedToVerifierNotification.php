<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class ApplicationAssignedToVerifierNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Application $application,
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
            'type' => 'application_assigned_to_verifier',
            'application_id' => $this->application->id,
            'branch_id' => $this->application->branch_id,
            'message' => 'Se te asignó la solicitud #'.$this->application->id.' para verificación en campo.',
        ];
    }
}
