<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Reconciliation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Reconciliation
 */
final class ReconciliationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'distributor_payment_id' => $this->distributor_payment_id,
            'bank_transaction_id' => $this->bank_transaction_id,
            'reconciled_by_user_id' => $this->reconciled_by_user_id,
            'verified_by_user_id' => $this->verified_by_user_id,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'reconciled_at' => $this->reconciled_at?->toIso8601String(),
            'reconciled_amount' => $this->reconciled_amount,
            'amount_difference' => $this->amount_difference,
            'status' => $this->status?->value,
            'notes' => $this->notes,
            'distributor_payment' => $this->whenLoaded('distributorPayment', fn () => [
                'id' => $this->distributorPayment->id,
                'cutoff_relation_id' => $this->distributorPayment->cutoff_relation_id,
                'distributor_id' => $this->distributorPayment->distributor_id,
                'amount' => $this->distributorPayment->amount,
                'reported_reference' => $this->distributorPayment->reported_reference,
                'status' => $this->distributorPayment->status?->value,
            ]),
        ];
    }
}