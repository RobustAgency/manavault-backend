<?php

namespace App\Clients\Ezcards;

class Vouchers extends Client
{
    public function getCodes(int $transactionID): array
    {
        $response = $this->getClient()->get('/v2/orders/'.$transactionID.'/codes');
        $response = $this->handleResponse($response);

        return $response;
    }
}
