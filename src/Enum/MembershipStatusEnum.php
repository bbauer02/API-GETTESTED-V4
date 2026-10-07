<?php

namespace App\Enum;

enum MembershipStatusEnum: string
{
    case PENDING  = 'PENDING';
    case ACTIVE   = 'ACTIVE';
    case ARCHIVED = 'ARCHIVED';
}
