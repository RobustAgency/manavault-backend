<?php

namespace App\Clients\Gift2Games;


class Products extends Client
{
    /**
     * Fetch a list of products from the Gift2Games API.
     *
     * @param array $queryParams Query parameters e.g., 'category', 'ids', 'inStock'
     * @return array The list of products
     * @throws \Exception
     */
    public function fetchList(array $queryParams = []): array
    {
        $response = $this->getClient()
            ->get('products', $queryParams);

        return $this->handleResponse($response);
    }
}
