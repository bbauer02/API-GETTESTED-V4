<?php

namespace App\Enum;

enum BusinessTypeEnum: string
{
    case ENROLLMENT = 'ENROLLMENT';
    case TEST_LICENSE = 'TEST_LICENSE';
    case PLATFORM_COMMISSION = 'PLATFORM_COMMISSION';
}
