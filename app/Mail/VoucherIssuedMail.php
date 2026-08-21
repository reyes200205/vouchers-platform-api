<?php

declare(strict_types=1);

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Se manda apenas la distribuidora pide el vale (ver RequestVoucherService),
 * no cuando la cajera lo aprueba: el cliente necesita esta informacion para
 * poder ir a "ferearlo" con la cajera. No depende de un modelo Voucher (que
 * todavia no existe en ese momento), solo de los datos ya calculados de la
 * solicitud.
 */
final class VoucherIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $customerName,
        public readonly string $distributorName,
        public readonly string $voucherNumber,
        public readonly Carbon $issuedAt,
        public readonly ?Carbon $expirationDate,
        public readonly float $amount,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Tu vale {$this->voucherNumber} ha sido emitido",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.voucher-issued',
            with: [
                'customerName' => $this->customerName,
                'distributorName' => $this->distributorName,
                'voucherNumber' => $this->voucherNumber,
                'issuedAt' => $this->issuedAt,
                'expirationDate' => $this->expirationDate,
                'amount' => $this->amount,
            ],
        );
    }
}
