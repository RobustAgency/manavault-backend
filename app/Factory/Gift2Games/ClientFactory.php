<?php

namespace App\Factory\Gift2Games;

use App\Clients\Gift2GamesClient;

class ClientFactory
{
    private const SLUG_TO_CONFIG = [
        'gift2games' => 'services.gift2games',
        'gift-2-games-eur' => 'services.gift2games_eur',
        'gift-2-games-gbp' => 'services.gift2games_gbp',
    ];

    public function getConfigPrefix(string $supplierSlug): string
    {
        return self::SLUG_TO_CONFIG[$supplierSlug]
            ?? throw new \InvalidArgumentException("Unknown Gift2Games supplier slug: {$supplierSlug}");
    }

    public function makeClient(string $supplierSlug): Gift2GamesClient
    {
        return new Gift2GamesClient($this->getConfigPrefix($supplierSlug));
    }
}
