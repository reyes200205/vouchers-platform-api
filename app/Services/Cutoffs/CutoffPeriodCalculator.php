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
 * recien dispersado: la regla de negocio (revision del profesor) es que un
 * vale otorgado cae en el periodo ACTUAL — el que ya esta corriendo, tenga o
 * no un corte generado todavia — no en el que sigue. Asi, si ya existe un
 * corte abierto para ese periodo, la nueva relacion aparece ahi al
 * reprocesarlo (ver ReprocessCutoffService); si el corte de ese periodo
 * todavia no se genera, aparece la primera vez que se genere. Antes se usaba
 * nextPeriodEnd() para esto (saltar siempre al periodo siguiente); se deja
 * el metodo por si hace falta en otro lado, pero ApproveVoucherService ya NO
 * lo usa.
 */
final class CutoffPeriodCalculator
{
    /**
     * Fecha final (ultimo dia) del periodo en el que cae $from.
     */
    public function currentPeriodEnd(Carbon $from, ?int $cutoffDay): Carbon
    {
        $cutoffDay = $cutoffDay !== null && $cutoffDay >= 1 && $cutoffDay <= 31 ? $cutoffDay : 15;

        if ($from->day <= $cutoffDay) {
            // $from cae en el primer periodo (1 - cutoffDay): termina en
            // cutoffDay de este mismo mes (ajustado si el mes no tiene tantos
            // dias, ej. cutoffDay 30 en febrero).
            $day = min($cutoffDay, $from->daysInMonth);

            return $from->copy()->day($day)->startOfDay();
        }

        // $from cae en el segundo periodo (cutoffDay+1 - fin de mes): termina
        // a fin de este mismo mes.
        return $from->copy()->endOfMonth()->startOfDay();
    }

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
