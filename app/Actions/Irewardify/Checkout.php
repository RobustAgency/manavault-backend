<?php

namespace App\Actions\Irewardify;

use App\Clients\Irewardify\Client;

class Checkout
{
    public function __construct(private Client $client) {}

    /**
     * @throws \RuntimeException
     */
    public function execute(array $payload): array
    {
        try {
            return $this->client->checkout($payload);
        } catch (\Exception $e) {
            throw new \RuntimeException($e->getMessage());
        }
    }
}
