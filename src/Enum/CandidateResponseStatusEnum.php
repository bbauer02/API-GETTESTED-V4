<?php

namespace App\Enum;

enum CandidateResponseStatusEnum: string
{
    case CORRECT   = 'CORRECT';
    case INCORRECT = 'INCORRECT';
    case PARTIAL   = 'PARTIAL';
    case SKIPPED   = 'SKIPPED';
}
