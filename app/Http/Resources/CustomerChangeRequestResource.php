<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CustomerChangeRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomerChangeRequest
 */
final class CustomerChangeRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'change_type' => $this->change_type?->value,
            'old_values' => $this->old_values_json,
            'new_values' => $this->new_values_json,
            'evidence' => $this->evidence_json,
            'status' => $this->status?->value,
            'rejection_reason' => $this->rejection_reason,
            'applied_at' => $this->applied_at?->toIso8601String(),
            'requested_by_user_id' => $this->requested_by_user_id,
            'approved_by_user_id' => $this->approved_by_user_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}