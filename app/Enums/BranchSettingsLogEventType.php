<?php

declare(strict_types=1);

namespace App\Enums;

enum BranchSettingsLogEventType: string
{
    case SUCURSAL = 'SUCURSAL';
    case CATEGORIA = 'CATEGORIA';
    case PRODUCTO = 'PRODUCTO';
}
