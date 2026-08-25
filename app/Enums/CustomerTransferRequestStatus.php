<?php

declare(strict_types=1);

namespace App\Enums;

enum CustomerTransferRequestStatus: string
{
    case PENDIENTE_DESTINO = 'PENDIENTE_DESTINO';
    case RECHAZADA_DESTINO = 'RECHAZADA_DESTINO';
    case PENDIENTE_COORDINADOR = 'PENDIENTE_COORDINADOR';
    case RECHAZADA_COORDINADOR = 'RECHAZADA_COORDINADOR';
    case AUTORIZADA = 'AUTORIZADA';
    case EJECUTADA = 'EJECUTADA';
    case CANCELADA = 'CANCELADA';
}
