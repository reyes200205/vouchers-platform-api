<?php

declare(strict_types=1);

namespace App\Enums;

enum BankAccountOwnerType: string
{
    case PERSONA = 'PERSONA';
    case DISTRIBUIDORA = 'DISTRIBUIDORA';
    case EMPRESA = 'EMPRESA';
}
