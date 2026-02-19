<?php

namespace App\Enum;

enum SessionValidationEnum: string
{
    case DRAFT     = 'DRAFT';
    case OPEN      = 'OPEN';
    case CLOSE     = 'CLOSE';
    case CANCELLED = 'CANCELLED';
}
