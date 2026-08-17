<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CutoffRelation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CutoffRelation
 */
final class CutoffRelationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cutoff_id' => $this->cutoff_id,
            'distributor_id' => $this->distributor_id,
            'previous_relation_id' => $this->previous_relation_id,
            'relation_number' => $this->relation_number,
            'payment_reference' => $this->payment_reference,
            'payment_due_date' => $this->payment_due_date?->toDateString(),
            'early_payment_start_date' => $this->early_payment_start_date?->toDateString(),
            'early_payment_end_date' => $this->early_payment_end_date?->toDateString(),
            'credit_limit_snapshot' => $this->credit_limit_snapshot,
            'available_credit_snapshot' => $this->available_credit_snapshot,
            'points_snapshot' => $this->points_snapshot,
            'total_commission' => $this->total_commission,
            'total_payment' => $this->total_payment,
            'total_late_fees' => $this->total_late_fees,
            'total_carryover_received' => $this->total_carryover_received,
            'total_amount_due' => $this->total_amount_due,
            'status' => $this->status?->value,
            'closed_by_carryover_at' => $this->closed_by_carryover_at?->toIso8601String(),
            'generated_at' => $this->generated_at?->toIso8601String(),
            'distributor' => $this->whenLoaded('distributor', fn () => [
                'id' => $this->distributor->id,
                'distributor_number' => $this->distributor->distributor_number,
                'business_name' => $this->distributor->business_name,
            ]),
            'items' => $this->whenLoaded('items', fn () => CutoffRelationItemResource::collection($this->items)),
        ];
    }
}