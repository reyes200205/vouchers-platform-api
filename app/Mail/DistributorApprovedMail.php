<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Se manda apenas el gerente aprueba la solicitud (ver
 * DecideApplicationService), para que la distribuidora pueda entrar de
 * inmediato con la contrasena temporal que ya se genero en ese mismo paso.
 */
final class DistributorApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $distributorName,
        public readonly string $loginEmail,
        public readonly string $temporaryPassword,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu solicitud para ser distribuidor fue aprobada',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.distributor-approved',
            with: [
                'distributorName' => $this->distributorName,
                'loginEmail' => $this->loginEmail,
                'temporaryPassword' => $this->temporaryPassword,
            ],
        );
    }
}
