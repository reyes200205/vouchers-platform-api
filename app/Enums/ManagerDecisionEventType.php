<?php

declare(strict_types=1);

namespace App\Enums;

enum ManagerDecisionEventType: string
{
    case NUEVA_DISTRIBUIDORA = 'NUEVA_DISTRIBUIDORA';
    case INCREMENTO_LIMITE = 'INCREMENTO_LIMITE';
    case INCREMENTO_MANUAL = 'INCREMENTO_MANUAL';
    case INCREMENTO_SUGERIDO_APROBADO = 'INCREMENTO_SUGERIDO_APROBADO';
    case APROBACION = 'APROBACION';
    case RECHAZO = 'RECHAZO';
}
