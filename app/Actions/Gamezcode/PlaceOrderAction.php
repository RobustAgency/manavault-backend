<?php

namespace App\Actions\Gamezcode;

use App\Clients\Gamezcode\Client;

class PlaceOrderAction
{
    public function __construct(private Client $gamezCodeClient) {}

    /**
     * Place an order with Gamezcode (Kalixo).
     *
     * @param  string  $externalOrderCode  Unique internal order identifier
     * @param  int  $totalPrice  Total price in smallest currency unit
     * @param  string  $currency  Currency code (e.g. 'GBP')
     * @param  array  $orderProducts  Array of Kalixo order product line items
     * @return array Response containing orderId, products with PINs, and optional wallet info
     *
     * @throws \RuntimeException If the order placement fails
     */
    public function execute(
        string $externalOrderCode,
        int $totalPrice,
        string $currency,
        array $orderProducts
    ): array {
        $response = $this->gamezCodeClient->placeOrder(
            $externalOrderCode,
            $totalPrice,
            $currency,
            $orderProducts
        );

        if (! isset($response['orderId'])) {
            throw new \RuntimeException(
                'Gamezcode place order failed - no orderId in response: '.json_encode($response)
            );
        }

        return $response;
    }
}
