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

        if ($staff->isGeneralManager() && ! $actor->hasRole('super-admin')) {
            abort(403, 'Solo el super administrador puede modificar a un gerente general.');
        }

        if (isset($data['role_code'])) {
            $targetRole = Role::query()->where('code', $data['role_code'])->firstOrFail();
            if ($targetRole->name === 'general_manager' && ! $actor->hasRole('super-admin')) {
                abort(403, 'Solo el super administrador puede asignar el rol de gerente general.');
            }
        }

        if ($actor->id === $staff->id && ! $actor->hasRole('super-admin')) {
            abort(403, 'No puedes modificar tu propia cuenta desde el módulo de personal.');
        }

        if (! $actor->isGeneralManager() && ! $actor->hasRole('super-admin')) {
            $allowedBranchIds = $actor->activeBusinessBranchIds();
            $staffBranchIds = $staff->activeBusinessBranchIds();

            $staffRole = $staff->businessRoles()->wherePivotNull('revoked_at')->first();
            abort_unless(
                $staffRole !== null && in_array($staffRole->name, ListStaffService::BRANCH_MANAGER_ROLES, true),
                403,
                'Solo puedes administrar personal subordinado (cajeras, coordinadores, verificadores).'
            );

            abort_unless(array_intersect($staffBranchIds, $allowedBranchIds) !== [], 403, 'Solo puedes administrar personal de tus sucursales.');
            abort_unless(($data['branch_id'] ?? null) === null || in_array($data['branch_id'], $allowedBranchIds, true), 403, 'Solo puedes asignar personal a tus sucursales.');

            if (isset($data['role_code'])) {
                $targetRole = Role::query()->where('code', $data['role_code'])->firstOrFail();
                abort_unless(
                    in_array($targetRole->name, ListStaffService::BRANCH_MANAGER_ROLES, true),
                    403,
                    'Solo el gerente general puede asignar otro tipo de rol.'
                );
            }
        } elseif (isset($data['role_code'])) {
            $targetRole = Role::query()->where('code', $data['role_code'])->firstOrFail();
            abort_unless(in_array($targetRole->name, ListStaffService::STAFF_ROLES, true), 422, 'El rol seleccionado no es administrable desde el módulo de personal.');
        }

        return DB::transaction(function () use ($actor, $staff, $data): User {
            $staff->update(['is_active' => $data['is_active']]);

            if (! $data['is_active']) {
                $staff->tokens()->delete();
            }

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

            // No filtramos por is_primary: usuarios sembrados via UserSeeder/syncRoles()
            // nunca marcaron su pivot como primario (Spatie::syncRoles no setea columnas
            // extra), y un miembro del modulo de personal solo tiene un rol de negocio
            // activo a la vez, asi que el primero no revocado es, de facto, el primario.
            $primaryPivot = $staff->businessRoles()
                ->wherePivotNull('revoked_at')
                ->whereIn('roles.name', ListStaffService::STAFF_ROLES)
                ->first();

            // branch_id tal cual vino en la request, antes de forzarlo a null para
            // gerente general (ver abajo): lo necesitamos para home_branch_id, que
            // es un dato informativo separado del branch_id del pivot de permisos.
            $requestedBranchId = array_key_exists('branch_id', $data) ? $data['branch_id'] : null;

            $branchId = $data['branch_id'] ?? ($primaryPivot?->pivot->branch_id ?? null);

            if (($data['role_code'] ?? null) !== null) {
                $newRole = Role::query()->where('code', $data['role_code'])->firstOrFail();
                if ($newRole->name === 'general_manager') {
                    $branchId = null;
                }
            } elseif ($staff->isGeneralManager()) {
                $branchId = null;
            }

            if (($data['role_code'] ?? null) !== null && $primaryPivot !== null && $primaryPivot->name !== $data['role_code']) {
                $newRole = Role::query()->where('code', $data['role_code'])->firstOrFail();

                if ($newRole->name === 'branch_manager' && $branchId !== null) {
                    $hasOtherActiveManager = User::query()
                        ->where('id', '!=', $staff->id)
                        ->whereHas('businessRoles', fn ($q) => $q->where('roles.name', 'branch_manager')
                            ->where('model_has_roles.branch_id', $branchId)
                            ->whereNull('model_has_roles.revoked_at'))
                        ->exists();

                    abort_if($hasOtherActiveManager, 422, 'Esta sucursal ya tiene un gerente asignado. Cambia su rol antes de asignar uno nuevo.');
                }

                if ($newRole->name === 'general_manager' && ! empty($requestedBranchId)) {
                    $hasActiveManager = User::query()
                        ->where('id', '!=', $staff->id)
                        ->whereHas('businessRoles', fn ($q) => $q->where('roles.name', 'branch_manager')
                            ->where('model_has_roles.branch_id', $requestedBranchId)
                            ->whereNull('model_has_roles.revoked_at'))
                        ->exists();

                    abort_if($hasActiveManager, 422, 'Esta sucursal ya tiene un gerente de sucursal asignado.');
                }

                $staff->update([
                    'home_branch_id' => $newRole->name === 'general_manager' ? $requestedBranchId : null,
                ]);

                $staff->businessRoles()->updateExistingPivot($primaryPivot->id, [
                    'revoked_at' => now(),
                    'is_primary' => false,
                ]);

                // Buscamos cualquier fila existente para este rol+sucursal (activa o revocada),
                // no solo revocadas: el mismo rol puede haberse asignado por otra vía (p. ej.
                // BranchController::store/update asigna 'branch_manager' directo con
                // Spatie::assignRole(), sin pasar por este flujo de revoked_at/is_primary), y
                // un attach() a ciegas choca con la unique (branch_id, role_id, model_id, model_type).
                $existingPivotQuery = $staff->businessRoles()->where('roles.id', $newRole->id);
                $existingPivotQuery = $branchId === null
                    ? $existingPivotQuery->whereNull('model_has_roles.branch_id')
                    : $existingPivotQuery->where('model_has_roles.branch_id', $branchId);
                $existingPivot = $existingPivotQuery->first();

                if ($existingPivot !== null) {
                    $staff->businessRoles()->updateExistingPivot($existingPivot->id, [
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
            } elseif ($primaryPivot !== null && $primaryPivot->name === 'general_manager' && array_key_exists('branch_id', $data)) {
                // El rol no cambia (sigue gerente general): solo actualizamos su
                // sucursal "base" informativa, sin tocar el pivot (que sigue null).
                if (! empty($requestedBranchId)) {
                    $hasActiveManager = User::query()
                        ->where('id', '!=', $staff->id)
                        ->whereHas('businessRoles', fn ($q) => $q->where('roles.name', 'branch_manager')
                            ->where('model_has_roles.branch_id', $requestedBranchId)
                            ->whereNull('model_has_roles.revoked_at'))
                        ->exists();

                    abort_if($hasActiveManager, 422, 'Esta sucursal ya tiene un gerente de sucursal asignado.');
                }

                $staff->update(['home_branch_id' => $requestedBranchId]);
            } elseif ($branchId !== null && $primaryPivot !== null && (int) $primaryPivot->pivot->branch_id !== $branchId) {
                if ($primaryPivot->name === 'branch_manager') {
                    $hasOtherActiveManager = User::query()
                        ->where('id', '!=', $staff->id)
                        ->whereHas('businessRoles', fn ($q) => $q->where('roles.name', 'branch_manager')
                            ->where('model_has_roles.branch_id', $branchId)
                            ->whereNull('model_has_roles.revoked_at'))
                        ->exists();

                    abort_if($hasOtherActiveManager, 422, 'Esta sucursal ya tiene un gerente asignado. Cambia su rol antes de asignar uno nuevo.');
                }

                $staff->businessRoles()->updateExistingPivot($primaryPivot->id, ['branch_id' => $branchId]);
            }

            return $staff->fresh(['person', 'businessRoles', 'homeBranch']);
        });
    }
}