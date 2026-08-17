<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Cutoff;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Cutoff
 */
final class CutoffResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'cutoff_type' => $this->cutoff_type?->value,
            'base_day_of_month' => $this->base_day_of_month,
            'base_time' => $this->base_time,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'executed_at' => $this->executed_at?->toIso8601String(),
            'status' => $this->status?->value,
            'config_snapshot_json' => $this->config_snapshot_json,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'relations_count' => $this->whenCounted('relations'),
            'total_amount_due' => $this->when(isset($this->relations_sum_total_amount_due), $this->relations_sum_total_amount_due),
            'relations' => $this->whenLoaded('relations', fn () => CutoffRelationResource::collection($this->relations)),
        ];
    }
}