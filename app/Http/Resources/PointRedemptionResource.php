<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PointRedemption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PointRedemption
 */
final class PointRedemptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'folio' => $this->folio,
            'distributor_id' => $this->distributor_id,
            'branch_id' => $this->branch_id,
            'requested_by_user_id' => $this->requested_by_user_id,
            'points' => $this->points,
            'point_value_snapshot' => $this->point_value_snapshot,
            'amount_mxn' => $this->amount_mxn,
            'status' => $this->status?->value,
            'decided_by_user_id' => $this->decided_by_user_id,
            'decision_notes' => $this->decision_notes,
            'decided_at' => $this->decided_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'distributor' => $this->whenLoaded('distributor', fn () => [
                'id' => $this->distributor->id,
                'distributor_number' => $this->distributor->distributor_number,
                'current_points' => $this->distributor->current_points,
            ]),
        ];
    }
}