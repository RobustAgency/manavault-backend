<?php

namespace App\Enums;

enum VoucherCodeStatus: string
{
    case PROCESSING = 'PROCESSING';
    case COMPLETED = 'COMPLETED';
}
