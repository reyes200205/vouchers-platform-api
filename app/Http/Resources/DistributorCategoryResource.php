<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DistributorCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DistributorCategory
 */
final class DistributorCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'code' => $this->code,
            'name' => $this->name,
            'commission_percentage' => $this->commission_percentage,
            'points_per_1200' => $this->points_per_1200,
            'late_penalty_percentage' => $this->late_penalty_percentage,
            'is_active' => $this->is_active,
        ];
    }
}