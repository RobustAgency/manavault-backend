<?php

namespace App\Actions\Giftery;

use App\Clients\Giftery\Client;

class GetProductsAction
{
    public function __construct(private Client $gifteryClient) {}

    public function execute(): array
    {
        return $this->gifteryClient->getProducts();
    }
}
