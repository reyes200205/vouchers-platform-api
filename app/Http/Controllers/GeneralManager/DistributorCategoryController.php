<?php

declare(strict_types=1);

namespace App\Http\Controllers\GeneralManager;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Categories\StoreDistributorCategoryRequest;
use App\Http\Requests\Categories\UpdateDistributorCategoryRequest;
use App\Http\Resources\DistributorCategoryResource;
use App\Models\DistributorCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DistributorCategoryController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $categories = DistributorCategory::query()
            ->when($request->has('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success(
            DistributorCategoryResource::collection($categories)->response()->getData(true)
        );
    }

    public function show(DistributorCategory $distributorCategory): JsonResponse
    {
        return $this->success(new DistributorCategoryResource($distributorCategory));
    }

    public function store(StoreDistributorCategoryRequest $request): JsonResponse
    {
        $category = DistributorCategory::query()->create($request->validated());

        return $this->created(new DistributorCategoryResource($category));
    }

    public function update(UpdateDistributorCategoryRequest $request, DistributorCategory $distributorCategory): JsonResponse
    {
        $distributorCategory->update($request->validated());

        return $this->success(new DistributorCategoryResource($distributorCategory->refresh()));
    }
}