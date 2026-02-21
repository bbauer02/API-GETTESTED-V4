<?php

namespace App\Enum;

enum EnrollmentExamStatusEnum: string
{
    case REGISTERED = 'REGISTERED';
    case PASSED = 'PASSED';
    case FAILED = 'FAILED';
}
