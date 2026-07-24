<?php

namespace App\Actions\Gamezcode;

use App\Clients\Gamezcode\Client;

class PlaceOrder
{
    public function __construct(private Client $client) {}

    /**
     * Place an order with Gamezcode.
     */
    public function execute(array $orderData): array
    {
        return $this->client->placeOrder($orderData);
    }
}
