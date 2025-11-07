<?php

namespace App\Clients\Gift2Games;

class Order extends Client
{
    public function createOrder(array $orderData): array
    {
        $response = $this->getClient()->post('/create_order', $orderData);

        $response = $this->handleResponse($response);

        if (!$response['status']) {
            throw new \RuntimeException('Order creation failed: ' . $response['error']['message']);
        }

        return $response;
    }
}
