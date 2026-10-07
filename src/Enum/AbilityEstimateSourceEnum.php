<?php

namespace App\Enum;

enum AbilityEstimateSourceEnum: string
{
    case EXAM        = 'EXAM';
    case PRACTICE    = 'PRACTICE';
    case CALIBRATION = 'CALIBRATION';
}
