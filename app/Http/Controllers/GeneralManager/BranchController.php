<?php

declare(strict_types=1);

namespace App\Http\Controllers\GeneralManager;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Branches\StoreBranchRequest;
use App\Http\Requests\Branches\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $branches = $query->paginate($request->integer('per_page', 15))
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
        $users = User::role(['general_manager', 'branch_manager'])
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

        $branch = Branch::query()->create($data);

        if ($request->filled('manager_user_id')) {
            $manager = User::findOrFail($request->manager_user_id);
            app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
            $manager->assignRole('branch_manager');
        }

        $audit->record($request, 'BRANCH_CREATED', 'branches', 'Sucursal creada.', $branch->id);

        return $this->created(new BranchResource($branch));
    }

    public function update(UpdateBranchRequest $request, Branch $branch, AuditLogger $audit): JsonResponse
    {
        $validatedData = $request->validated();
        $before = $branch->only(array_keys($request->safe()->except('manager_user_id')));
        $branch->update($request->safe()->except('manager_user_id'));

        if ($request->has('manager_user_id')) {
            $managerUserId = $request->input('manager_user_id');

            app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($branch->id);
            $existingManagers = User::role('branch_manager')->get();
            foreach ($existingManagers as $exManager) {
                $exManager->removeRole('branch_manager');
            }

            if ($managerUserId) {
                $manager = User::findOrFail($managerUserId);
                $manager->assignRole('branch_manager');
            }
        }

        $audit->record($request, 'BRANCH_UPDATED', 'branches', 'Sucursal actualizada.', $branch->id, [
            'before' => $before,
            'after' => $branch->fresh()->only(array_keys($request->safe()->except('manager_user_id'))),
        ]);

        return $this->success(new BranchResource($branch->fresh()));
    }
}