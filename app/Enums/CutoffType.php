<?php

declare(strict_types=1);

namespace App\Enums;

enum CutoffType: string
{
    case PAGOS = 'PAGOS';
    case PUNTOS = 'PUNTOS';
    case MIXTO = 'MIXTO';
}
