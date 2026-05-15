<?php

namespace App\Actions\Giftery;

use App\Clients\Giftery\Client;

class PlaceOrderAction
{
    public function __construct(private Client $gifteryClient) {}

    /**
     * Place an order with Giftery using the two-step reserve + confirm flow.
     *
     * @param  array  $reservePayload  Payload for the reserve endpoint
     * @return array Confirmed order data (may or may not include codes)
     *
     * @throws \RuntimeException If reserve or confirm fails
     */
    public function execute(array $reservePayload): array
    {
        $reserveResponse = $this->gifteryClient->reserveOrder($reservePayload);

        if (($reserveResponse['statusCode'] ?? -1) !== 0) {
            throw new \RuntimeException(
                'Giftery reserve failed: '.($reserveResponse['message'] ?? 'Unknown error')
            );
        }

        $transactionUUID = $reserveResponse['data']['transactionUUID']
            ?? throw new \RuntimeException('Giftery reserve response missing transaction UUID');

        $confirmResponse = $this->gifteryClient->confirmOrder($transactionUUID);

        return [
            'transactionUUID' => $transactionUUID,
            'confirmResponse' => $confirmResponse,
        ];
    }
}
