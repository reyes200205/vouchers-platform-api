<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Voucher;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class VoucherIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Voucher $voucher,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Tu vale {$this->voucher->voucher_number} ha sido emitido",
        );
    }

    public function content(): Content
    {
        $branchSetting = $this->voucher->branch?->setting;

        $expirationDate = $branchSetting?->voucher_expiration_days !== null && $this->voucher->issued_at !== null
            ? $this->voucher->issued_at->copy()->addDays((int) $branchSetting->voucher_expiration_days)
            : null;

        $person = $this->voucher->customer?->person;
        $distributorPerson = $this->voucher->distributor?->person;

        return new Content(
            view: 'emails.voucher-issued',
            with: [
                'customerName' => trim(($person?->first_name ?? '').' '.($person?->last_name ?? '')) ?: 'Cliente',
                'distributorName' => trim(($distributorPerson?->first_name ?? '').' '.($distributorPerson?->last_name ?? '')) ?: 'Tu distribuidora',
                'voucherNumber' => $this->voucher->voucher_number,
                'issuedAt' => $this->voucher->issued_at,
                'expirationDate' => $expirationDate,
                'amount' => (float) $this->voucher->amount,
            ],
        );
    }
}
