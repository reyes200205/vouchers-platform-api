<?php

declare(strict_types=1);

namespace App\Services\Cutoffs;

use Illuminate\Support\Carbon;

/**
 * Calcula los limites de los periodos de corte de una sucursal (calendario
 * fijo: del 1 al `cutoffDay` del mes, y de `cutoffDay + 1` al ultimo dia del
 * mes — ej. con cutoffDay = 15: periodos 1-15 y 16-31).
 *
 * Se usa para fijar la primera fecha de pago (payment_due_date) de un vale
 * recien dispersado: la regla de negocio es que un vale SIEMPRE cae en el
 * periodo que sigue al periodo donde se dispersa, nunca en el periodo actual
 * (aunque se haya dispersado el dia 1) — el cliente apenas recibio el vale,
 * no le puede tocar pagar en los proximos dias.
 */
final class CutoffPeriodCalculator
{
    /**
     * Fecha final (ultimo dia) del periodo que sigue al periodo donde cae
     * $from.
     */
    public function nextPeriodEnd(Carbon $from, ?int $cutoffDay): Carbon
    {
        $cutoffDay = $cutoffDay !== null && $cutoffDay >= 1 && $cutoffDay <= 31 ? $cutoffDay : 15;

        if ($from->day <= $cutoffDay) {
            // $from cae en el primer periodo (1 - cutoffDay): el periodo
            // actual termina en cutoffDay, el que sigue termina a fin de mes.
            return $from->copy()->endOfMonth()->startOfDay();
        }

        // $from cae en el segundo periodo (cutoffDay+1 - fin de mes): el
        // periodo actual termina a fin de mes, el que sigue termina en
        // cutoffDay del mes siguiente.
        $nextMonth = $from->copy()->addMonthNoOverflow()->startOfMonth();
        $day = min($cutoffDay, $nextMonth->daysInMonth);

        return $nextMonth->day($day)->startOfDay();
    }
}
