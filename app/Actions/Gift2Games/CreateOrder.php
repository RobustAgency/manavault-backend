<?php

namespace App\Actions\Gift2Games;

use App\Factory\G2GClient\ClientFactory;

class CreateOrder
{
    public function __construct(private ClientFactory $clientFactory) {}

    public function execute(array $orderData, string $supplierSlug, int $count): array
    {
        return $this->clientFactory->make($supplierSlug)->createOrders($orderData, $count);
    }
}
