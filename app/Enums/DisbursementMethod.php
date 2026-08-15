<?php

declare(strict_types=1);

namespace App\Enums;

enum DisbursementMethod: string
{
    case TRANSFERENCIA = 'TRANSFERENCIA';
    case EFECTIVO = 'EFECTIVO';
    case MIXTO = 'MIXTO';
}
