<?php

namespace App\Actions\Gamezcode;

use App\Clients\Gamezcode\Client;

class GetProducts
{
    public function __construct(private Client $client) {}

    /**
     * Fetch the product catalog from Gamezcode.
     */
    public function execute(int $take = 20, int $skip = 0): array
    {
        return $this->client->getProducts($take, $skip);
    }
}
