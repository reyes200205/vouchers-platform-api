<?php

declare(strict_types=1);

namespace App\Http\Controllers\GeneralManager;

use App\Enums\AuditEventType;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Points\DecidePointRedemptionRequest;
use App\Http\Requests\Points\UpdateDistributorCategoryRequest;
use App\Http\Resources\PointRedemptionResource;
use App\Models\Distributor;
use App\Models\PointRedemption;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Points\DecidePointRedemptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PointController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $branchIds = $user->activeBusinessBranchIds();

        $redemptions = PointRedemption::query()
            ->with('distributor')
            ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->when($request->filled('distributor_number'), fn ($query) => $query->whereHas(
                'distributor',
                fn ($distributorQuery) => $distributorQuery->where('distributor_number', 'like', '%'.$request->string('distributor_number')->value().'%')
            ))
            ->latest('id')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success(
            PointRedemptionResource::collection($redemptions)->response()->getData(true)
        );
    }

    public function decide(DecidePointRedemptionRequest $request, PointRedemption $pointRedemption, DecidePointRedemptionService $service, AuditLogger $audit): JsonResponse
    {
        $redemption = $service->execute($request->user(), $pointRedemption, $request->validated());

        $audit->record(
            $request,
            AuditEventType::Decided,
            'points',
            'Canje de puntos resuelto.',
            $redemption->branch_id,
            [
                'point_redemption_id' => $redemption->id,
                'status' => $redemption->status->value,
                'amount_mxn' => $redemption->amount_mxn,
            ]
        );

        return $this->success(new PointRedemptionResource($redemption->load('distributor')));
    }

    public function updateCategory(UpdateDistributorCategoryRequest $request, Distributor $distributor, AuditLogger $audit): JsonResponse
    {
        $oldCategoryId = $distributor->category_id;

        $distributor->update(['category_id' => $request->validated('category_id')]);

        $audit->record(
            $request,
            AuditEventType::Changed,
            'points',
            'Categoría de la distribuidora actualizada.',
            $distributor->branch_id,
            [
                'distributor_id' => $distributor->id,
                'category_id' => $distributor->category_id,
            ],
            null,
            ['category_id' => $oldCategoryId]
        );

        return $this->success([
            'id' => $distributor->id,
            'category_id' => $distributor->category_id,
        ]);
    }
}