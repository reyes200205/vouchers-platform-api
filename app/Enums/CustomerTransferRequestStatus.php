<?php

declare(strict_types=1);

namespace App\Enums;

enum CustomerTransferRequestStatus: string
{
    case PENDIENTE_COORDINADOR = 'PENDIENTE_COORDINADOR';
    case APROBADA_CODIGO_EMITIDO = 'APROBADA_CODIGO_EMITIDO';
    case RECHAZADA = 'RECHAZADA';
    case CANCELADA = 'CANCELADA';
    case EJECUTADA = 'EJECUTADA';
    case EXPIRADA = 'EXPIRADA';
}
