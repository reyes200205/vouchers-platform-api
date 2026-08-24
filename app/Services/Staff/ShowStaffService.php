<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Models\User;

final class ShowStaffService
{
    /**
     * Consulta un miembro del personal, con el mismo alcance de visibilidad
     * que ListStaffService: gerente general/super-admin ven a cualquiera,
     * gerente de sucursal solo a quienes tienen un rol administrable por el
     * dentro de sus sucursales activas.
     */
    public function execute(User $actor, User $target): User
    {
        $target->loadMissing(['person', 'businessRoles', 'homeBranch']);

        $isStaff = $target->businessRoles()
            ->whereIn('roles.name', ListStaffService::STAFF_ROLES)
            ->whereNull('model_has_roles.revoked_at')
            ->exists();

        abort_unless($isStaff, 404, 'Miembro del personal no encontrado.');

        if ($actor->isGeneralManager() || $actor->hasRole('super-admin')) {
            return $target;
        }

        $branchIds = $actor->activeBusinessBranchIds();

        $inScope = $target->id !== $actor->id && $target->businessRoles()
            ->whereIn('model_has_roles.branch_id', $branchIds)
            ->whereIn('roles.name', ListStaffService::BRANCH_MANAGER_ROLES)
            ->whereNull('model_has_roles.revoked_at')
            ->exists();

        abort_unless($inScope, 403, 'Solo puedes consultar personal de tus sucursales.');

        return $target;
    }
}
