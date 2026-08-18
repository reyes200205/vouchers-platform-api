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
                'middle_name' => $this->person?->middle_name,
                'last_name' => $this->person?->last_name,
                'second_last_name' => $this->person?->second_last_name,
                'gender' => $this->person?->gender?->value,
                'birth_date' => $this->person?->birth_date?->toDateString(),
                'curp' => $this->person?->curp,
                'rfc' => $this->person?->rfc,
                'home_phone' => $this->person?->home_phone,
                'mobile_phone' => $this->person?->mobile_phone,
                'email' => $this->person?->email,
                'street' => $this->person?->street,
                'external_number' => $this->person?->external_number,
                'neighborhood' => $this->person?->neighborhood,
                'city' => $this->person?->city,
                'state' => $this->person?->state,
                'postal_code' => $this->person?->postal_code,
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
