<?php

namespace App\Enums;

enum VoucherCodeStatus: string
{
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case ALLOCATED = 'allocated';
}
