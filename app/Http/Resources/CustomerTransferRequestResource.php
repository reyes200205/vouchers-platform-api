<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CustomerTransferRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomerTransferRequest
 */
final class CustomerTransferRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'source_distributor_id' => $this->source_distributor_id,
            'destination_distributor_id' => $this->destination_distributor_id,
            'requested_by_user_id' => $this->requested_by_user_id,
            'destination_decided_by_user_id' => $this->destination_decided_by_user_id,
            'destination_decided_at' => $this->destination_decided_at?->toIso8601String(),
            'coordinator_user_id' => $this->coordinator_user_id,
            'coordinator_decided_at' => $this->coordinator_decided_at?->toIso8601String(),
            'finalized_by_user_id' => $this->finalized_by_user_id,
            'status' => $this->status?->value,
            'request_reason' => $this->request_reason,
            'rejection_reason' => $this->rejection_reason,
            'comments' => $this->comments,
            'executed_at' => $this->executed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'source_distributor' => new DistributorResource($this->whenLoaded('sourceDistributor')),
            'destination_distributor' => new DistributorResource($this->whenLoaded('destinationDistributor')),
        ];
    }
}