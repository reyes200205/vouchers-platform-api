<?php

declare(strict_types=1);

namespace App\Enums;

enum LoginChannel: string
{
    case WEB = 'WEB';
    case VPN_WEB = 'VPN_WEB';
    case MOVIL = 'MOVIL';
}
