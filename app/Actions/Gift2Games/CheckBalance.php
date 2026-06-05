<?php

namespace App\Actions\Gift2Games;

use App\Factory\G2GClient\ClientFactory;

class CheckBalance
{
    public function __construct(private ClientFactory $clientFactory) {}

    public function execute(string $supplierSlug): array
    {
        $client = $this->clientFactory->make($supplierSlug);

        return $client->checkBalance();
    }
}
