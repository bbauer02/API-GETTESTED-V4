<?php

namespace App\Enum;

enum InvoiceStatusEnum: string
{
    case DRAFT = 'DRAFT';
    case ISSUED = 'ISSUED';
    case PAID = 'PAID';
    case CANCELLED = 'CANCELLED';
}
