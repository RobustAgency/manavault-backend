<?php

namespace App\Clients\Gift2Games;

class Order extends Client
{
    public function placeOrder(array $orderData): array
    {
        $response = $this->getClient()->post('/create_order', $orderData);

        return $this->handleResponse($response);
    }
}
