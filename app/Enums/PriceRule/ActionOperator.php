<?php

namespace App\Enums\PriceRule;

enum ActionOperator: string
{
    case ADDITION = '+';
    case SUBTRACTION = '-';
    case EQUAL = '=';
}
