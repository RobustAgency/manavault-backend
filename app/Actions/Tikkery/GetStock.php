<?php

namespace App\Actions\Tikkery;

use App\Clients\Tikkery\Client;

class GetStock
{
    public function __construct(private Client $client) {}

    /**
     * Get stock availability for one or more SKUs.
     *
     * @param  string[]  $skus
     */
    public function execute(array $skus): array
    {
        return $this->client->getStock($skus);
    }
}
