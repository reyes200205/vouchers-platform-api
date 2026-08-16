<?php

declare(strict_types=1);

namespace App\Http\Controllers\Distributor;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Points\StorePointRedemptionRequest;
use App\Http\Resources\PointRedemptionResource;
use App\Models\Distributor;
use App\Models\PointRedemption;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Points\RequestPointRedemptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PointController extends ApiController
{
    public function redeem(StorePointRedemptionRequest $request, Distributor $distributor, RequestPointRedemptionService $service, AuditLogger $audit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->person_id !== $distributor->person_id) {
            abort(403, 'Solo la distribuidora dueña de la cuenta puede solicitar el canje.');
        }

        $redemption = $service->execute($user, $distributor, $request->validated());

        $audit->record(
            $request,
            'POINT_REDEMPTION_REQUESTED',
            'points',
            'Canje de puntos solicitado.',
            $distributor->branch_id,
            [
                'point_redemption_id' => $redemption->id,
                'points' => $redemption->points,
                'amount_mxn' => $redemption->amount_mxn,
            ]
        );

        return $this->created(new PointRedemptionResource($redemption->load('distributor')));
    }

    public function myRedemptions(Request $request, Distributor $distributor): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->person_id !== $distributor->person_id) {
            abort(403, 'Solo la distribuidora dueña de la cuenta puede ver sus canjes.');
        }

        $redemptions = PointRedemption::query()
            ->where('distributor_id', $distributor->id)
            ->latest('id')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return $this->success(
            PointRedemptionResource::collection($redemptions)->response()->getData(true)
        );
    }
}