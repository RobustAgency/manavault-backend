<?php

namespace App\Actions\Tikkery;

use App\Clients\Tikkery\Client;

class GetOrders
{
    public function __construct(private Client $client) {}

    /**
     * List previously created orders.
     */
    public function execute(int $limit = 100, int $offset = 0): array
    {
        return $this->client->listOrders($limit, $offset);
    }
}
