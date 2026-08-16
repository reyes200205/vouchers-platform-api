<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\BankTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BankTransaction
 */
final class BankTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_bank_account_id' => $this->company_bank_account_id,
            'reference' => $this->reference,
            'transaction_date' => $this->transaction_date?->toDateString(),
            'transaction_time' => $this->transaction_time,
            'amount' => $this->amount,
            'transaction_type' => $this->transaction_type,
            'transaction_number' => $this->transaction_number,
            'payer_name' => $this->payer_name,
            'raw_description' => $this->raw_description,
            'reconciled' => $this->whenLoaded('reconciliation', fn () => $this->reconciliation !== null, false),
            'reconciliation' => $this->whenLoaded('reconciliation', fn () => [
                'id' => $this->reconciliation->id,
                'status' => $this->reconciliation->status?->value,
                'amount_difference' => $this->reconciliation->amount_difference,
            ]),
        ];
    }
}