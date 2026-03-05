<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case PROCESSING = 'processing';
    case PARTIALLY_COMPLETED = 'partially_completed';
    case FAILED = 'failed';
}
