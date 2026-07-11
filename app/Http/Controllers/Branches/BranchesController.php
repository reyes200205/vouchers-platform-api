<?php

declare(strict_types=1);

namespace App\Http\Controllers\Branches;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Branches\StoreBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Services\Branches\StoreBranchService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

final class BranchesController extends ApiController
{
    /**
     * Branch.index.
     *
     * @response array{success: true, message: string, data: array{data: \App\Http\Resources\BranchResource[], links: array<string, mixed>, meta: array<string, mixed>}}
     */

    public function index(Request $request): JsonResponse
    {
        $branches = Branch::query()
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success(
            BranchResource::collection($branches)->response()->getData(true)
        );
    }

    public function show(int $id): JsonResponse
    {
        $branch = Branch::findOrFail($id);

        return $this->success(
            new BranchResource($branch)
        );
    }


    public function store(StoreBranchRequest $request, StoreBranchService $service): JsonResponse
    {
        $branch = $service->execute($request->validated());

        return $this->success(
            new BranchResource($branch)
        );
    }
}
