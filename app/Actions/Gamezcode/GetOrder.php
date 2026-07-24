<?php

namespace App\Actions\Gamezcode;

use App\Clients\Gamezcode\Client;

class GetOrder
{
    public function __construct(private Client $client) {}

    /**
     * Retrieve an order from Gamezcode by its reference
     * (the placement orderId or your externalOrderCode).
     */
    public function execute(string $reference): array
    {
        return $this->client->getOrder($reference);
    }
}
