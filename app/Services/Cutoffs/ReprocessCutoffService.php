<?php

declare(strict_types=1);

namespace App\Services\Cutoffs;

use App\Enums\CutoffStatus;
use App\Models\Cutoff;
use App\Models\CutoffRelation;
use App\Models\Distributor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reprocesar un corte NO crea otro corte: vuelve a revisar el MISMO periodo
 * (mismo Cutoff) por si desde que se generó aparecieron distribuidoras que
 * ahora sí califican (por ejemplo, un vale que se aprobó/entregó después y
 * cuyo payment_due_date cae dentro de este mismo periodo). Las relaciones que
 * ya existen en este corte (con o sin pagos) no se tocan — solo se generan
 * relaciones nuevas para las distribuidoras que todavía no tienen una aquí.
 */
final class ReprocessCutoffService
{
    public function __construct(
        private readonly GenerateCutoffService $generateCutoffService,
    ) {}

    public function execute(Cutoff $cutoff): Cutoff
    {
        if ($cutoff->status === CutoffStatus::PROGRAMADO) {
            abort(422, 'El corte aún no ha sido ejecutado.');
        }

        // Un corte CERRADO ya es un estado final (CloseCutoffService también
        // se niega a volver a cerrarlo) -- antes, reprocesar uno lo dejaba en
        // EJECUTADO otra vez sin que nadie lo pidiera explícitamente,
        // "reabriéndolo" como efecto secundario de solo buscar distribuidoras
        // nuevas. Si de verdad hace falta revisar un corte ya cerrado, eso
        // debe ser una acción explícita (reabrirlo primero), no un efecto
        // colateral de reprocesar.
        if ($cutoff->status === CutoffStatus::CERRADO) {
            abort(422, 'Este corte ya está cerrado; no se puede reprocesar.');
        }

        if ($cutoff->period_start === null) {
            abort(422, 'Este corte no tiene periodo guardado (se generó antes de esta actualización); genera un corte nuevo en su lugar.');
        }

        return DB::transaction(function () use ($cutoff): Cutoff {
            $periodStart = Carbon::parse($cutoff->period_start)->startOfDay();
            $periodEnd = Carbon::parse($cutoff->scheduled_at)->endOfDay();

            $existingDistributorIds = CutoffRelation::query()
                ->where('cutoff_id', $cutoff->id)
                ->pluck('distributor_id');

            $distributors = Distributor::query()
                ->where('branch_id', $cutoff->branch_id)
                ->whereNull('deactivated_at')
                ->whereNotIn('id', $existingDistributorIds)
                ->orderBy('id')
                ->get();

            foreach ($distributors as $distributor) {
                $this->generateCutoffService->generateRelation($cutoff, $distributor, $periodStart, $periodEnd);
            }

            $cutoff->update([
                'executed_at' => now(),
                'status' => CutoffStatus::EJECUTADO,
                'notes' => trim(($cutoff->notes !== null ? $cutoff->notes.' ' : '').'Reprocesado '.now()->toDateTimeString().'.'),
            ]);

            return $cutoff->refresh();
        });
    }
}
