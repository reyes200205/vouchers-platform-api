<?php

declare(strict_types=1);

namespace App\Http\Controllers\GeneralManager;

use App\Http\Controllers\ApiController;
use App\Http\Resources\CutoffResource;
use App\Models\Cutoff;
use App\Services\Audit\AuditLogger;
use App\Services\Cutoffs\ReprocessCutoffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CutoffController extends ApiController
{
    public function reprocess(Request $request, Cutoff $cutoff, ReprocessCutoffService $service, AuditLogger $audit): JsonResponse
    {
        $newCutoff = $service->execute($cutoff);

        $audit->record(
            $request,
            'CUTOFF_REPROCESSED',
            'cutoffs',
            'Corte reprocesado.',
            $cutoff->branch_id,
            [
                'origin_cutoff_id' => $cutoff->id,
                'new_cutoff_id' => $newCutoff->id,
            ]
        );

        return $this->created(new CutoffResource($newCutoff->load('relations.distributor', 'relations.items')));
    }
}