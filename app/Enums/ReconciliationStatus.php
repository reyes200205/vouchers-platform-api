<?php

declare(strict_types=1);

namespace App\Enums;

enum ReconciliationStatus: string
{
    case CONCILIADA = 'CONCILIADA';
    case CON_DIFERENCIA = 'CON_DIFERENCIA';
    case RECHAZADA = 'RECHAZADA';
}
