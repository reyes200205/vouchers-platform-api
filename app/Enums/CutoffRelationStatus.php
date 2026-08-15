<?php

declare(strict_types=1);

namespace App\Enums;

enum CutoffRelationStatus: string
{
    case GENERADA = 'GENERADA';
    case PAGADA = 'PAGADA';
    case PARCIAL = 'PARCIAL';
    case VENCIDA = 'VENCIDA';
    case CERRADA = 'CERRADA';
}
