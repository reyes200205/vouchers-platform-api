<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Staff\StoreStaffRequest;
use App\Http\Requests\Staff\UpdateStaffRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Staff\ListStaffService;
use App\Services\Staff\StaffAuditService;
use App\Services\Staff\StoreStaffService;
use App\Services\Staff\UpdateStaffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StaffController extends ApiController
{
    public function index(Request $request, ListStaffService $service): JsonResponse
    {
        $user = $request->user();

        $employees = $service->execute($user, [
            'role'      => $request->has('role') ? $request->string('role')->toString() : null,
            'branch_id' => $request->has('branch_id') ? $request->integer('branch_id') : null,
            'per_page'  => $request->integer('per_page', 15),
        ])->appends($request->query());

        return $this->success(
            UserResource::collection($employees)->response()->getData(true)
        );
    }

    public function store(StoreStaffRequest $request, StoreStaffService $service, StaffAuditService $audit): JsonResponse
    {
        $staff = $service->execute($request->user(), $request->safe()->toArray());

        $audit->recordCreated($request, $staff, $request->role_code, (int) $request->branch_id);

        return $this->created(
            new UserResource($staff),
            'Miembro del personal creado exitosamente'
        );
    }

    public function update(UpdateStaffRequest $request, User $user, UpdateStaffService $service, StaffAuditService $audit): JsonResponse
    {
        $old = $audit->snapshot($user);

        $staff = $service->execute($request->user(), $user, $request->safe()->toArray());

        $audit->recordUpdated($request, $staff, $old);

        return $this->success(new UserResource($staff), 'Miembro del personal actualizado exitosamente');
    }
}