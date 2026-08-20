<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
final class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_code' => $this->customer_code,
            'status' => $this->status?->value,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'verified_by_user_id' => $this->verified_by_user_id,
            'notes' => $this->notes,
            'person' => new PersonResource($this->whenLoaded('person')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'distributors' => DistributorResource::collection($this->whenLoaded('distributors')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}