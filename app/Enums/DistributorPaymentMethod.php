<?php

declare(strict_types=1);

namespace App\Enums;

enum DistributorPaymentMethod: string
{
    case TRANSFER = 'TRANSFER';
    case DEPOSIT = 'DEPOSIT';
    case OTHER = 'OTHER';
}
