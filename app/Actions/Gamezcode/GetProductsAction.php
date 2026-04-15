<?php

namespace App\Actions\Gamezcode;

use App\Clients\Gamezcode\Client;

class GetProductsAction
{
    public function __construct(private Client $gamezCodeClient) {}

    /**
     * Fetch all products from the Gamezcode (Kalixo) catalog.
     *
     * @return array Flat list of all product objects
     */
    public function execute(): array
    {
        return $this->gamezCodeClient->getAllProducts();
    }
}
