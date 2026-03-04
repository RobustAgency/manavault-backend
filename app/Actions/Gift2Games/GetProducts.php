<?php

namespace App\Actions\Gift2Games;

use App\Factory\Gift2Games\ClientFactory;

class GetProducts
{
    public function __construct(private ClientFactory $clientFactory) {}

    public function execute(string $supplierSlug = 'gift2games'): array
    {
        $client = $this->clientFactory->makeClient($supplierSlug);

        return $client->getProducts();
    }
}
