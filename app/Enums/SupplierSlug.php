<?php

namespace App\Enums;

enum SupplierSlug: string
{
    case EZ_CARDS = 'ez_cards';
    // case GIFT2GAMES   = 'gift2games';
    // case GIFTERY_API  = 'giftery-api';
    // case IREWARDIFY   = 'irewardify';
    // case TIKKERY      = 'tikkery';

    /**
     * Returns all active (uncommented) slugs as plain string values.
     *
     * @return string[]
     */
    public static function slugs(): array
    {
        return array_column(self::cases(), 'value');
    }
}
