<?php

declare(strict_types=1);

namespace App\Enums;

enum PasswordRequestStatus: string
{
    case PENDIENTE = 'PENDIENTE';
    case APROBADA = 'APROBADA';
    case RECHAZADA = 'RECHAZADA';
    case EXPIRADA = 'EXPIRADA';
}
