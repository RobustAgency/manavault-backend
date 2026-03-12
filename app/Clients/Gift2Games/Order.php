<?php

namespace App\Clients\Gift2Games;

class Order extends Client
{
    public function __construct(string $configPrefix = 'services.gift2games')
    {
        parent::__construct($configPrefix);
    }

    public function createOrder(array $orderData): array
    {
        $response = $this->getFormClient()->post('/create_order', $orderData);

        $response = $this->handleResponse($response);

        if (! $response['status']) {
            throw new \RuntimeException('Order creation failed: '.$response['error']['message']);
        }

        return $response;
    }
}
