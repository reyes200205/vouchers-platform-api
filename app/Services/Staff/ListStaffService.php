<?php

declare(strict_types=1);

namespace App\Services\Staff;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListStaffService
{
    /**
     * Roles de negocio administrables desde el módulo de personal.
     */
    public const STAFF_ROLES = ['coordinator', 'verifier', 'branch_manager', 'cashier', 'general_manager'];

    /**
     * Roles that a branch manager can create and manage within their own branch.
     */
    public const BRANCH_MANAGER_ROLES = ['cashier', 'coordinator', 'verifier'];

    /**
     * Lista el personal con filtros y restricciones por alcance de sucursal.
     *
     * @param  array{branch_id?: int, role?: string, per_page?: int}  $filters
     */
    public function execute(User $actor, array $filters = []): LengthAwarePaginator
    {
        $query = User::query()
            ->with(['person', 'businessRoles', 'homeBranch'])
            ->whereHas('businessRoles', function ($q): void {
                $q->whereIn('roles.name', self::STAFF_ROLES)
                    ->whereNull('model_has_roles.revoked_at');
            });

        if (isset($filters['role'])) {
            $query->whereHas('businessRoles', fn ($q) => $q
                ->where('roles.name', $filters['role'])
                ->whereNull('model_has_roles.revoked_at'));
        }

        if (! $actor->isGeneralManager() && ! $actor->hasRole('super-admin')) {
            $branchIds = $actor->activeBusinessBranchIds();
            $perPage = (int) ($filters['per_page'] ?? 15);
            $query = User::query()
                ->with(['person', 'businessRoles', 'homeBranch'])
                ->where('users.id', '!=', $actor->id)
                ->whereHas('businessRoles', fn ($q) => $q
                    ->whereIn('model_has_roles.branch_id', $branchIds)
                    ->whereIn('roles.name', self::BRANCH_MANAGER_ROLES)
                    ->whereNull('model_has_roles.revoked_at'));

            if (isset($filters['role'])) {
                $query->whereHas('businessRoles', fn ($q) => $q
                    ->where('roles.name', $filters['role'])
                    ->whereNull('model_has_roles.revoked_at'));
            }

            return $query->paginate($perPage);
        }

        if (isset($filters['branch_id'])) {
            $query->whereHas('businessRoles', fn ($q) => $q
                ->where('model_has_roles.branch_id', $filters['branch_id'])
                ->whereNull('model_has_roles.revoked_at'));
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }
}