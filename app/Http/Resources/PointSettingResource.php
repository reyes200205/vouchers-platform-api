<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PointSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PointSetting
 */
final class PointSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'point_divisor_factor' => $this->point_divisor_factor,
            'point_multiplier' => $this->point_multiplier,
            'late_penalty_percentage' => $this->late_penalty_percentage,
            'updated_by_user_id' => $this->updated_by_user_id,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}