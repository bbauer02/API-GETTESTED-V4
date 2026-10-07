<?php

namespace App\Enum;

enum SessionStatusEnum: string
{
    case DRAFT     = 'DRAFT';
    case OPEN      = 'OPEN';
    case LOCKED    = 'LOCKED';
    case VALIDATED = 'VALIDATED';
    case CANCELLED = 'CANCELLED';
}
