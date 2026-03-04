<?php

namespace App\Actions\Ezcards;

use App\Clients\EzcardsClient;

class GetVoucherCodes
{
    public function __construct(private EzcardsClient $client) {}

    public function execute(int $transactionID): array
    {
        return $this->client->getVoucherCodes($transactionID);
    }
}
