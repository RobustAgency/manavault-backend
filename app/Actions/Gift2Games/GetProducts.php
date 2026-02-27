<?php

namespace App\Actions\Gift2Games;

use App\Factory\G2GClient\ClientFactory;

class GetProducts
{
    public function __construct(private ClientFactory $clientFactory) {}

    public function execute(string $supplierSlug = 'gift2games'): array
    {
        $productsClient = $this->clientFactory->makeProductsClient($supplierSlug);

        return $productsClient->fetchList();
    }
}
