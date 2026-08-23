<?php

declare(strict_types=1);

namespace App\Http\Controllers\BranchManager;

use App\Enums\AuditEventType;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Categories\StoreDistributorCategoryRequest;
use App\Http\Requests\Categories\UpdateDistributorCategoryRequest;
use App\Http\Resources\DistributorCategoryResource;
use App\Models\Branch;
use App\Models\DistributorCategory;
use App\Services\Audit\AuditLogger;
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

    public function store(StoreDistributorCategoryRequest $request, Branch $branch, AuditLogger $audit): JsonResponse
    {
        $user = $request->user();
        // branch_id ya viene fusionado desde la URL en
        // StoreDistributorCategoryRequest::prepareForValidation() — se valida
        // ahí, no aquí, así que para cuando llegamos a este punto ya es parte
        // de $request->validated().
        $category = DistributorCategory::query()->create($request->validated());

        $audit->record(
            $request,
            AuditEventType::Created,
            'catalog',
            "Created distributor category " . $category->name,
            $user->activeBusinessBranchIds()[0] ?? null,
            [
                'user_id'               => $user->id,
                'branch_id'             => $branch->id,
                'category_id'           => $category->id,
                'code'                  => $category->code,
                'name'                  => $category->name,
                'commission_percentage' => $category->commission_percentage,
            ]
        );
        return $this->created(new DistributorCategoryResource($category));
    }

    public function update(UpdateDistributorCategoryRequest $request, Branch $branch, DistributorCategory $distributorCategory, AuditLogger $audit): JsonResponse
    {
        $user = $request->user();
        abort_unless($distributorCategory->branch_id === $branch->id, 404);

        $data = $request->validated();
        unset($data['branch_id']);

        // Capturamos los datos anteriores antes de actualizar
        $old = [
            'code'                  => $distributorCategory->code,
            'name'                  => $distributorCategory->name,
            'commission_percentage' => $distributorCategory->commission_percentage,
            'is_active'             => $distributorCategory->is_active,
        ];

        $distributorCategory->update($data);

        $audit->record(
            $request,
            AuditEventType::Updated,
            'catalog',
            "Updated distributor category " . $distributorCategory->name,
            $user->activeBusinessBranchIds()[0] ?? null,
            [
                'user_id'     => $user->id,
                'branch_id'   => $branch->id,
                'category_id' => $distributorCategory->id,
                'new_data'    => [
                    'code'                  => $distributorCategory->code,
                    'name'                  => $distributorCategory->name,
                    'commission_percentage' => $distributorCategory->commission_percentage,
                    'is_active'             => $distributorCategory->is_active,
                ],
            ],
            'warning',  // level
            $old    // old_data: estado anterior al cambio
        );

        return $this->success(new DistributorCategoryResource($distributorCategory->refresh()));
    }
}
