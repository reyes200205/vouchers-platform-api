<?php

declare(strict_types=1);

namespace App\Services\Employees;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListEmployeesService
{
    /**
     * List employees with optional filters and role-based restrictions.
     *
     * @param array{branch_id?: int, per_page?: int} $filters
     */
    public function execute(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Employee::query()->with(['user', 'person', 'branch']);

        if ($user->hasRole('general_manager')) {
            // General manager can see all or filter by a specific branch
            if (isset($filters['branch_id'])) {
                $query->where('branch_id', $filters['branch_id']);
            }
        } else {
            // Other roles can only view employees in their own branch
            $branchId = $user->employee->branch_id;
            $query->where('branch_id', $branchId);
        }

        $perPage = (int) ($filters['per_page'] ?? 15);

        return $query->paginate($perPage);
    }
}
