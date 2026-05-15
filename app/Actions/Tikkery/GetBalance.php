<?php

namespace App\Actions\Tikkery;

use App\Clients\Tikkery\Client;

class GetBalance
{
    public function __construct(private Client $client) {}

    /**
     * Get the account balance at the given date.
     */
    public function execute(string $date): array
    {
        return $this->client->getBalance($date);
    }
}
