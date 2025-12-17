<?php

namespace App\Enums\PriceRule;

enum Status: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'in_active';
}
