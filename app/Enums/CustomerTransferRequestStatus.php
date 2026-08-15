<?php

declare(strict_types=1);

namespace App\Enums;

enum CustomerTransferRequestStatus: string
{
    case PENDIENTE_COORDINADOR = 'PENDIENTE_COORDINADOR';
    case APROBADA = 'APROBADA';
    case RECHAZADA = 'RECHAZADA';
    case CANCELADA = 'CANCELADA';
    case EJECUTADA = 'EJECUTADA';
}