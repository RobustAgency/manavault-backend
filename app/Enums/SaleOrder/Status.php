<?php

namespace App\Enums\SaleOrder;

enum Status: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing'; // Partially or fully unfulfilled, awaiting stock
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
