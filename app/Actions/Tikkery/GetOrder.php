<?php

namespace App\Actions\Tikkery;

use App\Clients\Tikkery\Client;

class GetOrder
{
    public function __construct(private Client $client) {}

    /**
     * Get details of a single order by its order number.
     */
    public function execute(string $orderNumber): array
    {
        return $this->client->getOrder($orderNumber);
    }
}
