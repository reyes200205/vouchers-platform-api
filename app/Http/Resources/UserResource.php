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
                'email' => $this->person?->email,
            ]),
            'roles' => $this->whenLoaded('businessRoles', function () {
                $branchIds = $this->businessRoles
                    ->pluck('pivot.branch_id')
                    ->filter()
                    ->unique();

                $branches = $branchIds->isNotEmpty()
                    ? \App\Models\Branch::whereIn('id', $branchIds)->get()->keyBy('id')
                    : collect();

                return $this->businessRoles
                    ->filter(fn ($role) => $role->pivot->revoked_at === null)
                    ->map(fn ($role) => [
                        'code' => $role->code,
                        'name' => $role->name,
                        'branch_id' => $role->pivot->branch_id,
                        'branch_name' => $role->pivot->branch_id ? ($branches->get($role->pivot->branch_id)?->name ?? null) : null,
                        'is_primary' => (bool) $role->pivot->is_primary,
                    ])->values();
            }),
            'permissions' => $this->whenLoaded('businessRoles', function () {
                $abilities = config('business-authorization.abilities', []);
                $userRoleNames = $this->businessRoles
                    ->filter(fn ($role) => $role->pivot->revoked_at === null)
                    ->pluck('name')
                    ->toArray();

                $userAbilities = [];
                foreach ($abilities as $ability => $roles) {
                    if (array_intersect($roles, $userRoleNames) !== []) {
                        $userAbilities[] = $ability;
                    }
                }
                return $userAbilities;
            }),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
