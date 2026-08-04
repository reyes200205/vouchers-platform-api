<?php

declare(strict_types=1);

namespace App\Enums;

enum CutoffStatus: string
{
    case PROGRAMADO = 'PROGRAMADO';
    case EJECUTADO = 'EJECUTADO';
    case CERRADO = 'CERRADO';
    case REPROCESADO = 'REPROCESADO';
}
