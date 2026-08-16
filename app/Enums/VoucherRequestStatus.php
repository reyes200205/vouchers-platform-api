<?php

declare(strict_types=1);

namespace App\Enums;

enum VoucherRequestStatus: string
{
    case PENDIENTE = 'PENDIENTE';
    case APROBADO = 'APROBADO';
    case RECHAZADO = 'RECHAZADO';
    case CANCELADO = 'CANCELADO';
}