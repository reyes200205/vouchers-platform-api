<?php

declare(strict_types=1);

namespace App\Http\Controllers\GeneralManager;

use App\Enums\AuditEventType;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Branches\StoreBranchRequest;
use App\Http\Requests\Branches\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class BranchController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Branch::query();

        if ($user && ! $user->hasGlobalBusinessRole()) {
            $branchIds = $user->activeBusinessBranchIds();
            $query->whereIn('id', $branchIds);
        }

        $perPage = $request->integer('per_page', 15);
        if ($perPage === -1) {
            $perPage = $query->count() ?: 15;
        }

        $branches = $query->paginate($perPage)
            ->appends($request->query());

        return $this->success(
            BranchResource::collection($branches)->response()->getData(true)
        );
    }

    public function show(Branch $branch): JsonResponse
    {
        return $this->success(new BranchResource($branch));
    }

    public function availableManagers(): JsonResponse
    {
        $users = User::query()
            ->whereHas('businessRoles', function ($query) {
                $query->whereIn('roles.name', ['general_manager', 'branch_manager'])
                    ->whereNull('model_has_roles.revoked_at');
            })
            ->with('person')
            ->get();

        $formatted = $users->map(fn($user) => [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->person ? trim($user->person->first_name . ' ' . $user->person->last_name) : $user->username,
        ]);

        return $this->success($formatted);
    }

    public function verifiers(Branch $branch): JsonResponse
    {
        $users = User::query()
            ->whereHas('businessRoles', function ($query) use ($branch): void {
                $query->where('roles.name', 'verifier')
                    ->whereNull('model_has_roles.revoked_at')
                    ->where('model_has_roles.branch_id', $branch->id);
            })
            ->with('person')
            ->get();

        $formatted = $users->map(fn ($user) => [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->person ? trim($user->person->first_name . ' ' . $user->person->last_name) : $user->username,
        ]);

        return $this->success($formatted);
    }

    public function store(StoreBranchRequest $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->safe()->except('manager_user_id');

        if (!isset($data['code']) || empty($data['code'])) {
            $slug = \Illuminate\Support\Str::slug($request->name);
            $code = 'BR-' . strtoupper($slug);

            $originalCode = $code;
            $counter = 1;
            while (Branch::where('code', $code)->exists()) {
                $code = $originalCode . '-' . $counter;
                $counter++;
            }
            $data['code'] = $code;
        }

        $branch = DB::transaction(function () use ($data, $request): Branch {
            $branch = Branch::query()->create($data);

            if ($request->filled('manager_user_id')) {
                $manager = User::findOrFail($request->manager_user_id);
                $this->assignBranchManager($manager, $branch->id);
            }

            return $branch;
        });

        $audit->record($request, AuditEventType::Created, 'branches', 'Sucursal creada.', $branch->id);

        return $this->created(new BranchResource($branch->fresh()));
    }

    public function update(UpdateBranchRequest $request, Branch $branch, AuditLogger $audit): JsonResponse
    {
        $before = $branch->only(array_keys($request->safe()->except('manager_user_id')));

        DB::transaction(function () use ($request, $branch): void {
            $branch->update($request->safe()->except('manager_user_id'));

            if ($request->has('manager_user_id')) {
                $managerUserId = $request->input('manager_user_id');

                $this->revokeBranchManagers($branch->id);

                if ($managerUserId) {
                    $manager = User::findOrFail($managerUserId);
                    $this->assignBranchManager($manager, $branch->id);
                }
            }
        });

        $audit->record($request, AuditEventType::Updated, 'branches', 'Sucursal actualizada.', $branch->id, [
            'before' => $before,
            'after' => $branch->fresh()->only(array_keys($request->safe()->except('manager_user_id'))),
        ]);

        return $this->success(new BranchResource($branch->fresh()));
    }

    /**
     * Asigna (o reactiva) el rol `branch_manager` para $manager en $branchId.
     *
     * Escribe directamente sobre la relacion `businessRoles()` en vez de usar
     * Spatie::assignRole(), porque este ultimo ignora las columnas extra
     * (revoked_at/is_primary) que usa el modulo de Staff para el mismo rol,
     * y un insert a ciegas choca con la unique (branch_id, role_id, model_id, model_type)
     * cuando ya existe una fila (activa o revocada) para ese rol+sucursal.
     */
    private function assignBranchManager(User $manager, int $branchId): void
    {
        $role = Role::query()->where('name', 'branch_manager')->firstOrFail();

        $existingPivot = $manager->businessRoles()
            ->where('roles.id', $role->id)
            ->where('model_has_roles.branch_id', $branchId)
            ->first();

        if ($existingPivot !== null) {
            $manager->businessRoles()->updateExistingPivot($existingPivot->id, [
                'revoked_at' => null,
                'is_primary' => true,
            ]);
        } else {
            $manager->businessRoles()->attach($role, [
                'branch_id' => $branchId,
                'assigned_at' => now(),
                'is_primary' => true,
            ]);
        }
    }

    /**
     * Revoca (soft) el rol `branch_manager` de todos los usuarios que lo tengan
     * activo en $branchId, en vez de borrar la fila (Spatie::removeRole()), para
     * mantener consistencia con el historial que usa el modulo de Staff.
     */
    private function revokeBranchManagers(int $branchId): void
    {
        $role = Role::query()->where('name', 'branch_manager')->firstOrFail();

        $managers = User::query()
            ->whereHas('businessRoles', fn ($q) => $q->where('roles.id', $role->id)
                ->where('model_has_roles.branch_id', $branchId)
                ->whereNull('model_has_roles.revoked_at'))
            ->get();

        foreach ($managers as $manager) {
            $pivot = $manager->businessRoles()
                ->where('roles.id', $role->id)
                ->where('model_has_roles.branch_id', $branchId)
                ->whereNull('model_has_roles.revoked_at')
                ->first();

            if ($pivot !== null) {
                $manager->businessRoles()->updateExistingPivot($pivot->id, [
                    'revoked_at' => now(),
                    'is_primary' => false,
                ]);
            }
        }
    }
}