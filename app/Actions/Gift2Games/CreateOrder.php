<?php

namespace App\Actions\Gift2Games;

use App\Clients\Gift2Games\Order;

class CreateOrder
{
    public function __construct(private Order $order) {}

    public function execute(array $orderData): array
    {
        $data = [
            'productId' => $orderData['product_sku'],
            'referenceNumber' => $orderData['order_number'],
        ];

        return $this->order->createOrder($data);
    }
}
