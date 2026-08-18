<?php

declare(strict_types=1);

namespace App\Http\Controllers\GeneralManager;

use App\Http\Controllers\ApiController;
use App\Http\Requests\FinancialProducts\StoreFinancialProductRequest;
use App\Http\Requests\FinancialProducts\UpdateFinancialProductRequest;
use App\Http\Resources\FinancialProductResource;
use App\Models\FinancialProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FinancialProductController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $products = FinancialProduct::query()
            ->when($request->has('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success(
            FinancialProductResource::collection($products)->response()->getData(true)
        );
    }

    public function show(FinancialProduct $financialProduct): JsonResponse
    {
        return $this->success(new FinancialProductResource($financialProduct));
    }

    public function store(StoreFinancialProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['code'] = $data['code'] ?? 'VAL-GLOBAL-'.str_pad((string) (FinancialProduct::query()->count() + 1), 4, '0', STR_PAD_LEFT);

        $product = FinancialProduct::query()->create($data);

        return $this->created(new FinancialProductResource($product));
    }

    public function update(UpdateFinancialProductRequest $request, FinancialProduct $financialProduct): JsonResponse
    {
        $financialProduct->update($request->validated());

        return $this->success(new FinancialProductResource($financialProduct->refresh()));
    }
}