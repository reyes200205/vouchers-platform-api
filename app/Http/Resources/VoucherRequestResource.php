<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\VoucherRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VoucherRequest
 */
final class VoucherRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'distributor_id' => $this->distributor_id,
            'customer_id' => $this->customer_id,
            'financial_product_id' => $this->financial_product_id,
            'branch_id' => $this->branch_id,
            'requested_amount' => $this->requested_amount,
            'is_pre_vale' => $this->is_pre_vale,
            'status' => $this->status->value,
            'rejection_reason' => $this->rejection_reason,
            'snapshot' => $this->snapshot_json,
            'created_by_user_id' => $this->created_by_user_id,
            'decided_by_user_id' => $this->decided_by_user_id,
            'decided_at' => $this->decided_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'customer_code' => $this->customer->customer_code,
                'person' => new PersonResource($this->customer->person),
            ]),
            'financial_product' => $this->whenLoaded('financialProduct', fn () => [
                'id' => $this->financialProduct->id,
                'code' => $this->financialProduct->code,
                'name' => $this->financialProduct->name,
                'principal_amount' => $this->financialProduct->principal_amount,
            ]),
        ];
    }
}