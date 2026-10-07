<?php

namespace App\Enum;

enum QuestionStatusEnum: string
{
    case DRAFT      = 'DRAFT';
    case PRETESTING = 'PRETESTING';
    case CALIBRATED = 'CALIBRATED';
    case RETIRED    = 'RETIRED';
}
