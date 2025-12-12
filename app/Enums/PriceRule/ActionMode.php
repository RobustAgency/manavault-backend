<?php

namespace App\Enums\PriceRule;

enum ActionMode: string
{
    case ABSOLUTE = 'absolute';
    case PERCENTAGE = 'percentage';
}
