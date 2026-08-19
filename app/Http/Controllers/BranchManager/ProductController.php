<?php

declare(strict_types=1);

namespace App\Http\Controllers\BranchManager;

use App\Http\Controllers\ApiController;
use App\Http\Requests\FinancialProducts\StoreFinancialProductRequest;
use App\Http\Requests\FinancialProducts\UpdateFinancialProductRequest;
use App\Http\Resources\FinancialProductResource;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\DistributorCategory;
use App\Models\FinancialProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ProductController extends ApiController
{
    public function index(Request $request, Branch $branch): JsonResponse
    {
        $products = FinancialProduct::query()
            ->with('category')
            ->where(fn ($query) => $query
                ->where('branch_id', $branch->id)
                ->orWhereNull('branch_id'))
            ->when($request->has('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success(
            FinancialProductResource::collection($products)->response()->getData(true)
        );
    }

    public function store(StoreFinancialProductRequest $request, Branch $branch): JsonResponse
    {
        $data = $request->validated();
        $data['branch_id'] = $branch->id;
        $data['code'] = $data['code'] ?? $this->nextProductCode($branch);
        $data['disbursement_method'] ??= 'TRANSFERENCIA';

        // Si la sucursal no manda un valor explícito para comisión/interés/seguro,
        // se usan los valores configurados a nivel sucursal en vez de un 0 fijo:
        // así ninguna sucursal termina con un vale gratis por omisión. La multa
        // por atraso ya NO se configura por producto: es global de la sucursal
        // (branch_settings.late_payment_penalty_amount), ver RequestVoucherService.
        $tariff = BranchSetting::query()->where('branch_id', $branch->id)->first();

        $data['company_commission_percentage'] ??= $tariff?->opening_commission_percentage ?? '0.0000';
        $data['fortnightly_interest_percentage'] ??= $tariff?->biweekly_interest_percentage ?? '0.0000';

        if (! isset($data['insurance_amount'])) {
            $data['insurance_amount'] = $tariff?->insuranceAmountFor((float) $data['principal_amount']) ?? '0.00';
        }

        if (isset($data['category_id'])) {
            $ownsCategory = DistributorCategory::query()
                ->where('id', $data['category_id'])
                ->where('branch_id', $branch->id)
                ->exists();

            abort_unless($ownsCategory, 422);
        }

        $product = FinancialProduct::query()->create($data);

        return $this->created(new FinancialProductResource($product->load('category')));
    }

    public function update(UpdateFinancialProductRequest $request, Branch $branch, FinancialProduct $financialProduct): JsonResponse
    {
        abort_unless($financialProduct->branch_id === $branch->id, 404);

        $data = $request->validated();

        if (isset($data['category_id'])) {
            $ownsCategory = DistributorCategory::query()
                ->where('id', $data['category_id'])
                ->where('branch_id', $branch->id)
                ->exists();

            abort_unless($ownsCategory, 422);
        }

        $financialProduct->update($data);

        return $this->success(new FinancialProductResource($financialProduct->load('category')));
    }

    private function nextProductCode(Branch $branch): string
    {
        $prefix = 'VAL-'.Str::upper(Str::slug($branch->code, '-')).'-';
        $count = FinancialProduct::query()->where('branch_id', $branch->id)->count() + 1;

        return $prefix.mb_str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
