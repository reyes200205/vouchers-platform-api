<?php

declare(strict_types=1);

namespace App\Http\Controllers\GeneralManager;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Branches\StoreBranchRequest;
use App\Http\Requests\Branches\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BranchController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $branches = Branch::query()
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success(
            BranchResource::collection($branches)->response()->getData(true)
        );
    }

    public function show(Branch $branch): JsonResponse
    {
        return $this->success(new BranchResource($branch));
    }

    public function store(StoreBranchRequest $request, AuditLogger $audit): JsonResponse
    {
        $branch = Branch::query()->create($request->validated());
        $audit->record($request, 'BRANCH_CREATED', 'branches', 'Sucursal creada.', $branch->id);

        return $this->created(new BranchResource($branch));
    }

    public function update(UpdateBranchRequest $request, Branch $branch, AuditLogger $audit): JsonResponse
    {
        $before = $branch->only(array_keys($request->validated()));
        $branch->update($request->validated());
        $audit->record($request, 'BRANCH_UPDATED', 'branches', 'Sucursal actualizada.', $branch->id, [
            'before' => $before,
            'after' => $branch->fresh()->only(array_keys($request->validated())),
        ]);

        return $this->success(new BranchResource($branch->fresh()));
    }
}