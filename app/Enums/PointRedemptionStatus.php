<?php

declare(strict_types=1);

namespace App\Enums;

enum PointRedemptionStatus: string
{
    case PENDIENTE = 'PENDIENTE';
    case APROBADO = 'APROBADO';
    case RECHAZADO = 'RECHAZADO';
    case CANCELADO = 'CANCELADO';
}