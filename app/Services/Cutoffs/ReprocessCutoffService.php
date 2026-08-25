<?php

declare(strict_types=1);

namespace App\Services\Cutoffs;

use App\Enums\CutoffStatus;
use App\Models\Cutoff;
use App\Models\Distributor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reprocesar un corte NO crea otro corte: vuelve a revisar el MISMO periodo
 * (mismo Cutoff) por si desde que se generó aparecieron vales nuevos que
 * ahora sí califican (por ejemplo, un vale que se aprobó después y cuyo
 * payment_due_date cae dentro de este mismo periodo). Se revisan TODAS las
 * distribuidoras activas de la sucursal, no solo las que todavía no tienen
 * relación en este corte: generateRelation() es idempotente (ver su
 * docblock) -- si la distribuidora ya tiene relación aquí, solo le agrega
 * los vales nuevos que le falten (por ejemplo, un vale otorgado a OTRO
 * cliente de la misma distribuidora); si no tiene, crea la relación como de
 * costumbre. Antes esto se saltaba por completo a las distribuidoras que ya
 * tenían relación en este corte, así que un vale nuevo para un cliente
 * distinto de esa misma distribuidora quedaba invisible hasta el siguiente
 * periodo.
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

            // Ya no se excluye a las distribuidoras que ya tienen relación en
            // este corte -- generateRelation() decide por si sola, por
            // distribuidora, si hay algo nuevo que agregarle o si no hay
            // nada que hacer (ver su docblock).
            $distributors = Distributor::query()
                ->where('branch_id', $cutoff->branch_id)
                ->whereNull('deactivated_at')
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
