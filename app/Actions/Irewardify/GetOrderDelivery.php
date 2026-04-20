<?php

namespace App\Actions\Irewardify;

use App\Clients\Irewardify\Client;

class GetOrderDelivery
{
    public function __construct(private Client $client) {}

    public function execute(string $orderId): array
    {
        return $this->client->getOrderDelivery($orderId);
    }
}
