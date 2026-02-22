<?php

namespace App\Enum;

enum PaymentMethodEnum: string
{
    case STRIPE = 'STRIPE';
    case BANK_TRANSFER = 'BANK_TRANSFER';
    case OTHER = 'OTHER';
}
