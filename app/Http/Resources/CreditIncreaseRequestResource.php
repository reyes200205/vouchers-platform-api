<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CreditIncreaseRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CreditIncreaseRequest
 */
final class CreditIncreaseRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'distributor_id' => $this->distributor_id,
            'branch_id' => $this->branch_id,
            'requested_by_user_id' => $this->requested_by_user_id,
            'requested_amount' => $this->requested_amount,
            'reason' => $this->reason,
            'status' => $this->status?->value,
            'pre_authorized_amount' => $this->pre_authorized_amount,
            'pre_authorized_by_user_id' => $this->pre_authorized_by_user_id,
            'pre_authorized_at' => $this->pre_authorized_at?->toIso8601String(),
            'approved_amount' => $this->approved_amount,
            'decided_by_user_id' => $this->decided_by_user_id,
            'decision_notes' => $this->decision_notes,
            'decided_at' => $this->decided_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'distributor' => $this->whenLoaded('distributor', fn () => [
                'id' => $this->distributor->id,
                'distributor_number' => $this->distributor->distributor_number,
                'credit_limit' => $this->distributor->credit_limit,
                'available_credit' => $this->distributor->available_credit,
            ]),
        ];
    }
}