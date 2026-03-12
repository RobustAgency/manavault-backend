<?php

namespace App\Clients\Gift2Games;

class Products extends Client
{
    public function __construct(string $configPrefix = 'services.gift2games')
    {
        parent::__construct($configPrefix);
    }

    /**
     * Fetch a list of products from the Gift2Games API.
     */
    public function fetchList(): array
    {
        $response = $this->getClient()->get('products');
        $response = $this->handleResponse($response);

        return $response;
    }
}
