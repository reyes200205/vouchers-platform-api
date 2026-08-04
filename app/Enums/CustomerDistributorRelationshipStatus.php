<?php

declare(strict_types=1);

namespace App\Enums;

enum CustomerDistributorRelationshipStatus: string
{
    case ACTIVA = 'ACTIVA';
    case BLOQUEADA = 'BLOQUEADA';
    case TERMINADA = 'TERMINADA';
}
