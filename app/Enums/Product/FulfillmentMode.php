<?php

namespace App\Enums\Product;

enum FulfillmentMode: string
{
    case PRICE = 'price';
    case MANUAL = 'manual';
}
