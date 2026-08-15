<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Distributor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Distributor
 */
final class DistributorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'distributor_number' => $this->distributor_number,
            'status' => $this->status?->value,
            'credit_limit' => $this->credit_limit,
            'available_credit' => $this->available_credit,
            'unlimited_credit' => $this->unlimited_credit,
            'current_points' => $this->current_points,
            'can_issue_vouchers' => $this->can_issue_vouchers,
            'is_external' => $this->is_external,
            'person' => new PersonResource($this->whenLoaded('person')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'category' => new DistributorCategoryResource($this->whenLoaded('category')),
        ];
    }
}