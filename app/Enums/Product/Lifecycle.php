<?php

namespace App\Enums\Product;

enum Lifecycle: string
{
    case IN_ACTIVE = 'in_active';
    case ACTIVE = 'active';
    case ARCHIVED = 'archived';
}
