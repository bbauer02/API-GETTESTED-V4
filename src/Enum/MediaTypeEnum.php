<?php

namespace App\Enum;

enum MediaTypeEnum: string
{
    case IMAGE   = 'IMAGE';
    case AUDIO   = 'AUDIO';
    case VIDEO   = 'VIDEO';
    case YOUTUBE = 'YOUTUBE';
}
