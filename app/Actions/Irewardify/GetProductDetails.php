<?php

namespace App\Actions\Irewardify;

use App\Clients\Irewardify\Client;

class GetProductDetails
{
    public function __construct(private Client $client) {}

    public function execute(string $productId): array
    {
        return $this->client->getProductDetails($productId);
    }
}
