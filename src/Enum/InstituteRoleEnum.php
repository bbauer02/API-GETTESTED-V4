<?php

namespace App\Enum;

enum InstituteRoleEnum: string
{
    case ADMIN    = 'ADMIN';
    case TEACHER  = 'TEACHER';
    case STAFF    = 'STAFF';
    case CUSTOMER = 'CUSTOMER';
}
