<?php

declare(strict_types=1);

namespace App\Enums;

enum VerificationResult: string
{
    case PENDIENTE = 'PENDIENTE';
    case VERIFICADA = 'VERIFICADA';
    case RECHAZADA = 'RECHAZADA';
}
