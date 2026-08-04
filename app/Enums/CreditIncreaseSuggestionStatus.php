<?php

declare(strict_types=1);

namespace App\Enums;

enum CreditIncreaseSuggestionStatus: string
{
    case PENDIENTE = 'PENDIENTE';
    case APROBADA = 'APROBADA';
    case RECHAZADA = 'RECHAZADA';
}
