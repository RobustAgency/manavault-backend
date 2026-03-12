<?php

namespace App\Actions\Gift2Games;

use App\Factory\G2GClient\ClientFactory;

class CheckBalance
{
    public function __construct(private ClientFactory $clientFactory) {}

    public function execute(string $supplierSlug = 'gift2games'): array
    {
        $balanceClient = $this->clientFactory->makeBalanceClient($supplierSlug);

        return $balanceClient->checkBalance();
    }
}
