<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case PROCESSING = 'processing';
    case CANCELLED = 'cancelled';
}
