<?php

namespace App\Suppliers\Contracts;

use App\Suppliers\Support\PollResult;
use App\Suppliers\Support\PollContext;

interface PollsForVouchers
{
    public function pollVouchers(PollContext $context): PollResult;
}
