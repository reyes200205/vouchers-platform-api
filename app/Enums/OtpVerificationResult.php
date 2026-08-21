<?php

declare(strict_types=1);

namespace App\Enums;

enum OtpVerificationResult: string
{
    case OK = 'OK';
    case NOT_FOUND = 'NOT_FOUND';
    case EXPIRED = 'EXPIRED';
    case INCORRECT = 'INCORRECT';
    case RATE_LIMITED = 'RATE_LIMITED';

    public function isOk(): bool
    {
        return $this === self::OK;
    }
}
