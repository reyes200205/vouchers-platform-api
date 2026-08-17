<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'is_active' => $this->is_active,
            'requires_vpn' => $this->requires_vpn,
            'login_channel' => $this->login_channel?->value,
            'person' => $this->whenLoaded('person', fn () => [
                'id' => $this->person?->id,
                'first_name' => $this->person?->first_name,
                'last_name' => $this->person?->last_name,
            ]),
            'role' => $this->whenLoaded('role', fn () => $this->role ? [
                'code' => $this->role->code,
                'name' => $this->role->name,
            ] : null),
            'branch_id' => $this->branch_id,
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id' => $this->branch->id,
                'code' => $this->branch->code,
                'name' => $this->branch->name,
            ] : null),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
