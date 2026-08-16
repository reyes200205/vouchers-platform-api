<?php

declare(strict_types=1);

namespace App\Enums;

enum CreditIncreaseRequestStatus: string
{
    case PENDIENTE = 'PENDIENTE';
    case PRE_AUTORIZADO = 'PRE_AUTORIZADO';
    case APROBADO = 'APROBADO';
    case REDUCIDO = 'REDUCIDO';
    case RECHAZADO = 'RECHAZADO';
    case CANCELADO = 'CANCELADO';
}