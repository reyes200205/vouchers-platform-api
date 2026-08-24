<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Models\User;

/**
 * Centraliza la regla de "esta sucursal ya tiene gerente" para que Staff y
 * Branches revisen exactamente lo mismo: una fila activa de branch_manager,
 * o un gerente general que la tiene marcada como su sucursal base
 * (home_branch_id). Sin esto, cada pantalla validaba una sola mitad y se
 * podian pisar (dos gerentes generales reclamando la misma sucursal base,
 * o un branch_manager nuevo sobre la sucursal base de un gerente general).
 */
final class BranchManagerAvailability
{
    public static function hasActiveBranchManager(int $branchId, ?int $excludeUserId = null): bool
    {
        return User::query()
            ->when($excludeUserId !== null, fn ($q) => $q->where('id', '!=', $excludeUserId))
            ->whereHas('businessRoles', fn ($q) => $q->where('roles.name', 'branch_manager')
                ->where('model_has_roles.branch_id', $branchId)
                ->whereNull('model_has_roles.revoked_at'))
            ->exists();
    }

    public static function hasHomeBasedGeneralManager(int $branchId, ?int $excludeUserId = null): bool
    {
        return User::query()
            ->when($excludeUserId !== null, fn ($q) => $q->where('id', '!=', $excludeUserId))
            ->where('home_branch_id', $branchId)
            ->whereHas('businessRoles', fn ($q) => $q->where('roles.name', 'general_manager')
                ->whereNull('model_has_roles.revoked_at'))
            ->exists();
    }

    public static function hasAnyManager(int $branchId, ?int $excludeUserId = null): bool
    {
        return self::hasActiveBranchManager($branchId, $excludeUserId)
            || self::hasHomeBasedGeneralManager($branchId, $excludeUserId);
    }
}
