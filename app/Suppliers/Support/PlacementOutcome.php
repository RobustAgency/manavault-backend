<?php

namespace App\Suppliers\Support;

enum PlacementOutcome: string
{
    case VOUCHERS_READY = 'vouchers_ready';
    case AWAITING_VOUCHERS = 'awaiting_vouchers';
}
