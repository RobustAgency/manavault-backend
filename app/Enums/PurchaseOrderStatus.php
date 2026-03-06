<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case COMPLETED = 'completed';
    case PROCESSING = 'processing';
    case FAILED = 'failed';
}
