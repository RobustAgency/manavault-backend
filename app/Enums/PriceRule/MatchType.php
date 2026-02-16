<?php

namespace App\Enums\PriceRule;

enum MatchType: string
{
    case ALL = 'all';
    case ANY = 'any';
}
