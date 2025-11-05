<?php

namespace App\Actions\Ezcards;

use App\Clients\Ezcards\Orders;

class PlaceOrder
{
    public function __construct(private Orders $ezcardsOrders) {}

    /**
     * Place an order with EZ Cards.
     *
     * @param array $orderData The order data to be sent to EZ Cards API
     * @return array The response from the EZ Cards API
     * @throws \RuntimeException If the order placement fails
     */
    public function execute(array $orderData): array
    {
        try {
            $data = [
                'products' => [
                    [
                        'sku' => $orderData['product_sku'],
                        'quantity' => $orderData['quantity'],
                        'clientOrderNumber' => $orderData['order_number']
                    ],
                ],
            ];
            return $this->ezcardsOrders->placeOrder($data);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException($e->getMessage());
        }
    }
}
