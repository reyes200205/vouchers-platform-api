<?php

declare(strict_types=1);

namespace App\Services\Cutoffs;

use Illuminate\Support\Carbon;

/**
 * Calcula los limites de los periodos de corte de una sucursal (calendario
 * fijo: del 1 al `cutoffDay` del mes, y de `cutoffDay + 1` al ultimo dia del
 * mes — ej. con cutoffDay = 15: periodos 1-15 y 16-31).
 *
 * Se usan para fijar la primera fecha de pago (payment_due_date) de un vale
 * recien dispersado, pero SOLO como respaldo cuando la sucursal todavia no
 * tiene ningun corte generado (ver ApproveVoucherService, que primero
 * intenta usar el corte ACTUALMENTE ABIERTO si ya existe uno -- sin importar
 * que tan lejos este de la fecha real). Para ese respaldo se usa
 * nextPeriodEnd() (saltar al periodo siguiente), no currentPeriodEnd(): en
 * la practica el gerente genera el primer corte de una sucursal desde el
 * proximo limite de quincena "limpio" (ej. hoy 25-ago -> corte desde 1-sep),
 * no desde la mitad de la quincena que ya esta corriendo -- si el vale
 * quedara con fecha en la quincena en curso, caeria en un periodo que el
 * gerente nunca llega a generar y jamas aparece en ningun corte.
 * currentPeriodEnd() se deja disponible por si hace falta en otro lado.
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
