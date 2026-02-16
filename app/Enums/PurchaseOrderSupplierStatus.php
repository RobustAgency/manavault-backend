<?php

namespace App\Enums;

enum PurchaseOrderSupplierStatus: string
{
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
}
