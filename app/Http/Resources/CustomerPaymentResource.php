<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CustomerPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomerPayment
 */
final class CustomerPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'voucher_id' => $this->voucher_id,
            'customer_id' => $this->customer_id,
            'distributor_id' => $this->distributor_id,
            'collected_by_user_id' => $this->collected_by_user_id,
            'payment_date' => $this->payment_date?->toIso8601String(),
            'amount' => $this->amount,
            'payment_method' => $this->payment_method?->value,
            'is_partial' => $this->is_partial,
            'affects_points' => $this->affects_points,
            'notes' => $this->notes,
            'reversed_at' => $this->reversed_at?->toIso8601String(),
            'reversed_by_user_id' => $this->reversed_by_user_id,
            'reversal_reason' => $this->reversal_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'voucher' => $this->whenLoaded('voucher', fn () => [
                'id' => $this->voucher->id,
                'voucher_number' => $this->voucher->voucher_number,
                'status' => $this->voucher->status->value,
                'current_balance' => $this->voucher->current_balance,
            ]),
        ];
    }
}