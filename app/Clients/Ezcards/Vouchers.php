<?php

namespace App\Clients\Ezcards;

use Illuminate\Support\Facades\Log;

class Vouchers extends Client
{
    public function getCodes(int $transactionID): array
    {
        $response = $this->getClient()->get('/v2/orders/'.$transactionID.'/codes');
        $response = $this->handleResponse($response);
        Log::info('EZ Cards Voucher Codes Response', ['response' => $response]);

        return $response;
    }
}
