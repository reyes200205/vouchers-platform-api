<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\ApiController;
use App\Http\Resources\PointRedemptionResource;
use App\Models\PointRedemption;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Points\DecidePointRedemptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PointRedemptionController extends ApiController
{
    public function show(Request $request, string $folio): JsonResponse
    {
        $redemption = $this->findByFolio($request, $folio);

        return $this->success(new PointRedemptionResource($redemption->load('distributor')));
    }

    public function payout(Request $request, string $folio, DecidePointRedemptionService $service, AuditLogger $audit): JsonResponse
    {
        $redemption = $this->findByFolio($request, $folio);

        $redemption = $service->execute($request->user(), $redemption, ['decision' => 'APROBADO']);

        $audit->record(
            $request,
            'POINT_REDEMPTION_PAID_BY_CASHIER',
            'points',
            'Canje de puntos pagado por cajera.',
            $redemption->branch_id,
            [
                'point_redemption_id' => $redemption->id,
                'folio' => $redemption->folio,
                'amount_mxn' => $redemption->amount_mxn,
            ]
        );

        return $this->success(new PointRedemptionResource($redemption->load('distributor')));
    }

    private function findByFolio(Request $request, string $folio): PointRedemption
    {
        /** @var User $user */
        $user = $request->user();

        $redemption = PointRedemption::query()->where('folio', $folio)->firstOrFail();

        if (! in_array($redemption->branch_id, $user->activeBusinessBranchIds(), true)) {
            abort(403, 'El folio pertenece a otra sucursal.');
        }

        return $redemption;
    }
}
