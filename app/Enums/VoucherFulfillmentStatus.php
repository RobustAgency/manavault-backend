<?php

namespace App\Enums;

enum VoucherFulfillmentStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
}
