<?php

namespace App\Actions\Irewardify;

use App\Clients\Irewardify\Client;

class GetProducts
{
    public function __construct(private Client $client) {}

    public function execute(): array
    {
        $queryParams = [
            'category' => 'Digital',
        ];

        return $this->client->getProducts();
    }
}
