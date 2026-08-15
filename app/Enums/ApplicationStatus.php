<?php

declare(strict_types=1);

namespace App\Enums;

enum ApplicationStatus: string
{
    case PRE = 'PRE';
    case MODIFICADA = 'MODIFICADA';
    case EN_REVISION = 'EN_REVISION';
    case VERIFICADA = 'VERIFICADA';
    case POSIBLE_DISTRIBUIDORA = 'POSIBLE_DISTRIBUIDORA';
    case APROBADA = 'APROBADA';
    case RECHAZADA = 'RECHAZADA';
}
