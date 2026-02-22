<?php

namespace App\Enum;

enum InvoiceTypeEnum: string
{
    case INVOICE = 'INVOICE';
    case CREDIT_NOTE = 'CREDIT_NOTE';
}
