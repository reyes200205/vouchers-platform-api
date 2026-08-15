<?php

declare(strict_types=1);

namespace App\Enums;

enum ChangeType: string
{
    case IDENTITY = 'IDENTITY';
    case CONTACT = 'CONTACT';
    case EVIDENCE = 'EVIDENCE';
}