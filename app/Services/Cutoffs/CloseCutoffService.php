<?php

declare(strict_types=1);

namespace App\Services\Cutoffs;

use App\Enums\CutoffRelationStatus;
use App\Enums\CutoffStatus;
use App\Models\Cutoff;
use Illuminate\Support\Facades\DB;

/**
 * Cierra un corte a la fuerza, sin importar cuántos días falten para su
 * payment_due_date. Cualquier relación que siga GENERADA (nunca pagó) o
 * PARCIAL (pagó de menos) se marca VENCIDA con las mismas consecuencias que
 * el vencimiento automático (MarkOverdueRelationsService::closeRelation):
 * la distribuidora debe remitir la quincena completa (se le suma de vuelta
 * la comisión que se hubiera quedado) más la multa. Esa deuda queda lista
 * para arrastrarse como carryover al siguiente corte junto con la quincena
 * normal de ese periodo (GenerateCutoffService).
 *
 * Las relaciones ya PAGADA no se tocan.
 */
final class CloseCutoffService
{
    public function __construct(
        private readonly MarkOverdueRelationsService $markOverdueRelationsService,
    ) {}

    public function execute(Cutoff $cutoff): Cutoff
    {
        if (in_array($cutoff->status, [CutoffStatus::PROGRAMADO, CutoffStatus::CERRADO], true)) {
            abort(422, 'Este corte no se puede cerrar en su estado actual.');
        }

        return DB::transaction(function () use ($cutoff): Cutoff {
            $relations = $cutoff->relations()
                ->whereIn('status', [CutoffRelationStatus::GENERADA, CutoffRelationStatus::PARCIAL])
                ->get();

            foreach ($relations as $relation) {
                $this->markOverdueRelationsService->closeRelation($relation);
            }

            $cutoff->update(['status' => CutoffStatus::CERRADO]);

            return $cutoff->refresh();
        });
    }
}
