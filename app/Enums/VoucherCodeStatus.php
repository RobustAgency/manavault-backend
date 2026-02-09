<?php

namespace App\Enums;

enum VoucherCodeStatus: string
{
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case AVAILABLE = 'available';
    case ALLOCATED = 'allocated';
}
