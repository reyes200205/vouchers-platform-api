<?php

declare(strict_types=1);

namespace App\Enums;

enum PointMovementType: string
{
    case GANADO_ANTICIPADO = 'GANADO_ANTICIPADO';
    case GANADO_PUNTUAL = 'GANADO_PUNTUAL';
    case PENALIZACION_ATRASO = 'PENALIZACION_ATRASO';
    case AJUSTE_MANUAL = 'AJUSTE_MANUAL';
    case REVERSO = 'REVERSO';
    case CANJE = 'CANJE';
}
