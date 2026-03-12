<?php

namespace App\Factory\G2GClient;

use App\Clients\Gift2Games\Order;
use App\Clients\Gift2Games\Balance;
use App\Clients\Gift2Games\Products;

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

    public function makeProductsClient(string $supplierSlug): Products
    {
        return new Products($this->getConfigPrefix($supplierSlug));
    }

    public function makeOrderClient(string $supplierSlug): Order
    {
        return new Order($this->getConfigPrefix($supplierSlug));
    }

    public function makeBalanceClient(string $supplierSlug): Balance
    {
        return new Balance($this->getConfigPrefix($supplierSlug));
    }
}
