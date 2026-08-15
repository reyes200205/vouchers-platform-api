<?php

declare(strict_types=1);

namespace App\Enums;

enum ChangeRequestStatus: string
{
    case PENDIENTE = 'PENDIENTE';
    case APROBADA = 'APROBADA';
    case RECHAZADA = 'RECHAZADA';
}