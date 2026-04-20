<?php

namespace App\Actions\Tikkery;

use App\Clients\Tikkery\Client;

class CreateOrder
{
    public function __construct(private Client $client) {}

    /**
     * Create a new order with Tikkery.
     * Tikkery automatically pays it using the account balance.
     *
     * @throws \RuntimeException
     */
    public function execute(array $orderData): array
    {
        try {
            return $this->client->createOrder($orderData);
        } catch (\Exception $e) {
            throw new \RuntimeException($e->getMessage());
        }
    }
}
