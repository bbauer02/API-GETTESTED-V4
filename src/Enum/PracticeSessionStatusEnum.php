<?php

namespace App\Enum;

enum PracticeSessionStatusEnum: string
{
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETED   = 'COMPLETED';
    case ABANDONED   = 'ABANDONED';
}
