<?php

namespace App\Clients\Ezcards;

class Products extends Client
{
    /**
     * Fetch a list of products from the EZ Cards API.
     *
     * Query parameters:
     * - limit: Integer (1-1000, default: 1000) - Items per page
     * - page: Integer (default: 1) - Page number
     * - sku: String (optional) - Filter by SKU
     *
     * @param array $params Query parameters
     * @return array The list of products
     * @throws \Exception
     */
    public function list(array $params = []): array
    {
        $response = $this->getClient()->get('/v2/products', $params);

        return $this->handleResponse($response);
    }
}
