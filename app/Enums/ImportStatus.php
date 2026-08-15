<?php

declare(strict_types=1);

namespace App\Enums;

enum ImportStatus: string
{
    case COMPLETADO = 'COMPLETADO';
    case PARCIAL = 'PARCIAL';
    case ERROR = 'ERROR';
}