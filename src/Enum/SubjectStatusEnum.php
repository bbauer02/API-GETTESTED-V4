<?php

namespace App\Enum;

enum SubjectStatusEnum: string
{
    case DRAFT    = 'DRAFT';
    case LOCKED   = 'LOCKED';
    case ARCHIVED = 'ARCHIVED';
}
