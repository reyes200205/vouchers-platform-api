<?php

declare(strict_types=1);

namespace App\Enums;

enum CustomerStatus: string
{
    case EN_VERIFICACION = 'EN_VERIFICACION';
    case ACTIVO = 'ACTIVO';
    case BLOQUEADO = 'BLOQUEADO';
    case MOROSO = 'MOROSO';
    case INACTIVO = 'INACTIVO';
}
