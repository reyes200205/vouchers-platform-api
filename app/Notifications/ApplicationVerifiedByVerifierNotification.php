<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\VerificationResult;
use App\Models\Application;
use App\Models\ApplicationVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class ApplicationVerifiedByVerifierNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Application $application,
        public readonly ApplicationVerification $verification,
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
        $wasApproved = $this->verification->result === VerificationResult::VERIFICADA;

        return [
            'type' => 'application_verified_by_verifier',
            'application_id' => $this->application->id,
            'branch_id' => $this->application->branch_id,
            'result' => $this->verification->result->value,
            'message' => $wasApproved
                ? 'El verificador aprobó la solicitud #'.$this->application->id.' en la visita de campo.'
                : 'El verificador rechazó la solicitud #'.$this->application->id.' en la visita de campo.',
        ];
    }
}
