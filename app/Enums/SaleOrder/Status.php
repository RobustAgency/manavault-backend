<?php

namespace App\Enums\SaleOrder;

enum Status: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
