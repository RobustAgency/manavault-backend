<?php

namespace App\Enums;

enum PurchaseOrderItemStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case FULFILLED = 'fulfilled';
}
