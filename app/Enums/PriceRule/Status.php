<?php

namespace App\Enums\PriceRule;

enum Status: string
{
    case DRAFT = 'draft';
    case APPLIED = 'applied';
}
