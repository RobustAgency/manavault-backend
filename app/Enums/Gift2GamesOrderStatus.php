<?php

namespace App\Enums;

enum Gift2GamesOrderStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case FULFILLED = 'fulfilled';
}
