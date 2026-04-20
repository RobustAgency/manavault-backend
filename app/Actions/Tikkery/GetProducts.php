<?php

namespace App\Actions\Tikkery;

use App\Clients\Tikkery\Client;

class GetProducts
{
    public function __construct(private Client $client) {}

    /**
     * List available products from Tikkery.
     */
    public function execute(int $limit = 100, int $offset = 0): array
    {
        return $this->client->listProducts($limit, $offset);
    }
}
