<?php

declare(strict_types=1);

namespace App\Http\Controllers\BranchManager;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Categories\StoreDistributorCategoryRequest;
use App\Http\Requests\Categories\UpdateDistributorCategoryRequest;
use App\Http\Resources\DistributorCategoryResource;
use App\Models\Branch;
use App\Models\DistributorCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CategoryController extends ApiController
{
    public function index(Request $request, Branch $branch): JsonResponse
    {
        $categories = DistributorCategory::query()
            ->where('branch_id', $branch->id)
            ->when($request->has('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success(
            DistributorCategoryResource::collection($categories)->response()->getData(true)
        );
    }

    public function store(StoreDistributorCategoryRequest $request, Branch $branch): JsonResponse
    {
        // branch_id ya viene fusionado desde la URL en
        // StoreDistributorCategoryRequest::prepareForValidation() — se valida
        // ahí, no aquí, así que para cuando llegamos a este punto ya es parte
        // de $request->validated().
        $category = DistributorCategory::query()->create($request->validated());

        return $this->created(new DistributorCategoryResource($category));
    }

    public function update(UpdateDistributorCategoryRequest $request, Branch $branch, DistributorCategory $distributorCategory): JsonResponse
    {
        abort_unless($distributorCategory->branch_id === $branch->id, 404);

        $data = $request->validated();
        unset($data['branch_id']);

        $distributorCategory->update($data);

        return $this->success(new DistributorCategoryResource($distributorCategory->refresh()));
    }
}
