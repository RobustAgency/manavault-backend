<?php

namespace App\Actions\Gamezcode;

use App\Clients\Gamezcode\Client;

class GetOrderAction
{
    public function __construct(private Client $gamezCodeClient) {}

    /**
     * Retrieve a previously placed Gamezcode order by its Kalixo orderId.
     *
     * @param  string  $orderId  The Kalixo orderId returned from place-order
     * @return array Full order object including products, PINs, and wallet info
     */
    public function execute(string $orderId): array
    {
        return $this->gamezCodeClient->getOrder($orderId);
    }
}
