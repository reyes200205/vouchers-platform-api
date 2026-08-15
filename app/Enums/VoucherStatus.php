<?php

declare(strict_types=1);

namespace App\Enums;

enum VoucherStatus: string
{
    case BORRADOR = 'BORRADOR';
    case APROBADO = 'APROBADO';
    case TRANSFERIDO = 'TRANSFERIDO';
    case ACTIVO = 'ACTIVO';
    case PAGO_PARCIAL = 'PAGO_PARCIAL';
    case PAGADO = 'PAGADO';
    case LIQUIDADO = 'LIQUIDADO';
    case MOROSO = 'MOROSO';
    case RECLAMADO = 'RECLAMADO';
    case CANCELADO = 'CANCELADO';
    case REVERSADO = 'REVERSADO';
}
