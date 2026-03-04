<?php

namespace App\Actions\Ezcards;

use App\Clients\EzcardsClient;

class PlaceOrderAction
{
    public function __construct(
        private EzcardsClient $client
    ) {}

    /**
     * Place an order with EZ Cards.
     *
     * @param  array  $orderData  The order data to be sent to EZ Cards API
     * @return array The response from the EZ Cards API
     *
     * @throws \RuntimeException If the order placement fails
     */
    public function execute(array $orderData): array
    {
        try {
            return $this->client->createOrder($orderData);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException($e->getMessage());
        }
    }
}
