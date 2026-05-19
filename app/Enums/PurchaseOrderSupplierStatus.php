<?php

namespace App\Enums;

enum PurchaseOrderSupplierStatus: string
{
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case PENDING_VOUCHERS = 'pending_vouchers';
}
