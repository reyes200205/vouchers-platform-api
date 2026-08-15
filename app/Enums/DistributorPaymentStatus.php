<?php

declare(strict_types=1);

namespace App\Enums;

enum DistributorPaymentStatus: string
{
    case REPORTED = 'REPORTED';
    case DETECTED = 'DETECTED';
    case RECONCILED = 'RECONCILED';
    case REJECTED = 'REJECTED';
}
