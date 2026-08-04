<?php

declare(strict_types=1);

namespace App\Enums;

enum Gender: string
{
    case MALE = 'M';
    case FEMALE = 'F';
    case OTHER = 'OTHER';
}
