<?php

declare(strict_types=1);

namespace App\Enums;

enum DistributorStatus: string
{
    case CANDIDATA = 'CANDIDATA';
    case POSIBLE = 'POSIBLE';
    case ACTIVA = 'ACTIVA';
    case INACTIVA = 'INACTIVA';
    case MOROSA = 'MOROSA';
    case BLOQUEADA = 'BLOQUEADA';
    case CERRADA = 'CERRADA';
}
