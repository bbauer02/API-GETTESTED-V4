<?php

namespace App\Enum;

/**
 * Statut de validation d'un institut par la plateforme.
 * Seul un institut ACTIVE peut ouvrir des sessions aux inscriptions.
 */
enum InstituteStatusEnum: string
{
    case PENDING_REVIEW = 'PENDING_REVIEW';
    case ACTIVE         = 'ACTIVE';
    case SUSPENDED      = 'SUSPENDED';
}
