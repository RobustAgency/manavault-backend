<?php

namespace App\Actions\Gift2Games;

use App\Factory\Gift2Games\ClientFactory;

class CreateOrder
{
    public function __construct(private ClientFactory $clientFactory) {}

    public function execute(array $orderData, string $supplierSlug = 'gift2games'): array
    {
        $client = $this->clientFactory->makeClient($supplierSlug);

        try {
            $orderResponse = $client->createOrder($orderData);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException($e->getMessage());
        }

        return $orderResponse;
    }
}
