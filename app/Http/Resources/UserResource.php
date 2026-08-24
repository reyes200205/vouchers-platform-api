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
            // true mientras el usuario nunca haya confirmado o cambiado la
            // contrasena temporal (CURP) que se le asigno al darlo de alta —
            // el frontend usa esto para mostrar el modal de "deja esta
            // contrasena o cambiala" en su primer login.
            'requires_password_confirmation' => $this->password_confirmed_at === null,
            'login_channel' => $this->login_channel?->value,
            // Sucursal "base" de un gerente general: solo informativo, no limita
            // sus permisos (esos siguen siendo globales via businessRoles).
            'home_branch' => $this->whenLoaded('homeBranch', fn () => $this->homeBranch ? [
                'id' => $this->homeBranch->id,
                'name' => $this->homeBranch->name,
            ] : null),
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
            'distributor' => $this->whenLoaded('distributor', fn () => $this->distributor ? [
                'id' => $this->distributor->id,
                'distributor_number' => $this->distributor->distributor_number,
                'branch_id' => $this->distributor->branch_id,
                'status' => $this->distributor->status?->value,
                'credit_limit' => $this->distributor->credit_limit,
                'available_credit' => $this->distributor->available_credit,
                'unlimited_credit' => $this->distributor->unlimited_credit,
                'current_points' => $this->distributor->current_points,
                'can_issue_vouchers' => $this->distributor->can_issue_vouchers,
                // Monto maximo permitido para el proximo vale por la regla del
                // pre-vale (ver AuthController::attachPreValeMaxAmount). Null
                // significa que la regla no aplica: puede pedir hasta su
                // credito disponible normalmente.
                'pre_vale_max_amount' => $this->distributor->pre_vale_max_amount ?? null,
                'category' => $this->distributor->relationLoaded('category') && $this->distributor->category
                    ? [
                        'id' => $this->distributor->category->id,
                        'code' => $this->distributor->category->code,
                        'name' => $this->distributor->category->name,
                        'commission_percentage' => $this->distributor->category->commission_percentage,
                    ]
                    : null,
            ] : null),
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
