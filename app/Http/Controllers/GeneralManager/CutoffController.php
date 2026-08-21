<?php

declare(strict_types=1);

namespace App\Http\Controllers\GeneralManager;

use App\Http\Controllers\ApiController;
use App\Http\Resources\CutoffResource;
use App\Models\Cutoff;
use App\Services\Audit\AuditLogger;
use App\Services\Cutoffs\CloseCutoffService;
use App\Services\Cutoffs\ReprocessCutoffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CutoffController extends ApiController
{
    public function reprocess(Request $request, Cutoff $cutoff, ReprocessCutoffService $service, AuditLogger $audit): JsonResponse
    {
        $cutoff = $service->execute($cutoff);

        $audit->record(
            $request,
            'CUTOFF_REPROCESSED',
            'cutoffs',
            'Corte reprocesado (mismo periodo, se revisó si había nuevas relaciones).',
            $cutoff->branch_id,
            [
                'cutoff_id' => $cutoff->id,
            ]
        );

        return $this->success(new CutoffResource($cutoff->load('relations.distributor.person', 'relations.items.customer.person')));
    }

    public function close(Request $request, Cutoff $cutoff, CloseCutoffService $service, AuditLogger $audit): JsonResponse
    {
        $cutoff = $service->execute($cutoff);

        $audit->record(
            $request,
            'CUTOFF_CLOSED',
            'cutoffs',
            'Corte cerrado manualmente; las relaciones sin pagar quedaron vencidas.',
            $cutoff->branch_id,
            [
                'cutoff_id' => $cutoff->id,
            ]
        );

        return $this->success(new CutoffResource($cutoff->load('relations.distributor.person', 'relations.items.customer.person')));
    }
}