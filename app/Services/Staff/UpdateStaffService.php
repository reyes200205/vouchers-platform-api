<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateStaffService
{
    /**
     * Actualiza el estado, rol, sucursal o datos personales de un miembro del personal.
     *
     * @param  array{
     *     is_active: bool,
     *     role_code?: string|null,
     *     branch_id?: int|null,
     *     first_name?: string|null,
     *     middle_name?: string|null,
     *     last_name?: string|null,
     *     second_last_name?: string|null,
     *     gender?: string|null,
     *     birth_date?: string|null,
     *     curp?: string|null,
     *     rfc?: string|null,
     *     home_phone?: string|null,
     *     mobile_phone?: string|null,
     *     email?: string|null,
     *     street?: string|null,
     *     external_number?: string|null,
     *     neighborhood?: string|null,
     *     city?: string|null,
     *     state?: string|null,
     *     postal_code?: string|null
     * }  $data
     */
    public function execute(User $actor, User $staff, array $data): User
    {
        if (! $staff->businessRoles()->wherePivotNull('revoked_at')->whereIn('roles.name', ListStaffService::STAFF_ROLES)->exists()) {
            abort(422, 'El usuario no pertenece al módulo de personal.');
        }

        if (! $actor->isGeneralManager()) {
            $allowedBranchIds = $actor->activeBusinessBranchIds();
            $staffBranchIds = $staff->activeBusinessBranchIds();

            abort_unless(array_intersect($staffBranchIds, $allowedBranchIds) !== [], 403, 'Solo puedes administrar personal de tus sucursales.');
            abort_unless(($data['branch_id'] ?? null) === null || in_array($data['branch_id'], $allowedBranchIds, true), 403, 'Solo puedes asignar personal a tus sucursales.');

            if (isset($data['role_code'])) {
                $targetRole = Role::query()->where('code', $data['role_code'])->firstOrFail();
                abort_unless($targetRole->name === 'cashier', 403, 'Solo el gerente general puede asignar otro tipo de rol.');
            }
        } elseif (isset($data['role_code'])) {
            $targetRole = Role::query()->where('code', $data['role_code'])->firstOrFail();
            abort_unless(in_array($targetRole->name, ListStaffService::STAFF_ROLES, true), 422, 'El rol seleccionado no es administrable desde el módulo de personal.');
        }

        return DB::transaction(function () use ($actor, $staff, $data): User {
            $staff->update(['is_active' => $data['is_active']]);

            if ($staff->person !== null) {
                $personFields = [
                    'first_name', 'middle_name', 'last_name', 'second_last_name', 'gender',
                    'birth_date', 'curp', 'rfc', 'home_phone', 'mobile_phone', 'email',
                    'street', 'external_number', 'neighborhood', 'city', 'state', 'postal_code',
                ];

                $changes = array_intersect_key($data, array_flip($personFields));

                if ($changes !== []) {
                    $staff->person->update($changes);
                }
            }

            $primaryPivot = $staff->businessRoles()
                ->wherePivotNull('revoked_at')
                ->where('model_has_roles.is_primary', true)
                ->whereIn('roles.name', ListStaffService::STAFF_ROLES)
                ->first();

            $branchId = $data['branch_id'] ?? ($primaryPivot?->pivot->branch_id ?? null);

            if (($data['role_code'] ?? null) !== null && $primaryPivot !== null && $primaryPivot->name !== $data['role_code']) {
                $staff->businessRoles()->updateExistingPivot($primaryPivot->id, ['revoked_at' => now()]);

                $newRole = Role::query()->where('code', $data['role_code'])->firstOrFail();
                $revokedPivot = $staff->businessRoles()
                    ->wherePivotNotNull('revoked_at')
                    ->where('roles.id', $newRole->id)
                    ->first();

                if ($revokedPivot !== null) {
                    $staff->businessRoles()->updateExistingPivot($revokedPivot->id, [
                        'branch_id' => $branchId,
                        'revoked_at' => null,
                        'is_primary' => true,
                    ]);
                } else {
                    $staff->businessRoles()->attach($newRole, [
                        'branch_id' => $branchId,
                        'assigned_at' => now(),
                        'is_primary' => true,
                    ]);
                }
            } elseif ($branchId !== null && $primaryPivot !== null && (int) $primaryPivot->pivot->branch_id !== $branchId) {
                $staff->businessRoles()->updateExistingPivot($primaryPivot->id, ['branch_id' => $branchId]);
            }

            return $staff->fresh(['person', 'businessRoles']);
        });
    }
}