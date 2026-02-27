<?php

namespace App\Actions\Gift2Games;

use App\Factory\G2GClient\ClientFactory;

class CreateOrder
{
    public function __construct(private ClientFactory $clientFactory) {}

    public function execute(array $orderData, string $supplierSlug = 'gift2games'): array
    {
        $orderClient = $this->clientFactory->makeOrderClient($supplierSlug);

        try {
            $orderResponse = $orderClient->createOrder($orderData);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException($e->getMessage());
        }

        return $orderResponse;
    }
}
