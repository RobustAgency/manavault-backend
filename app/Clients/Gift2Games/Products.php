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
        $response = $this->getClient()->get('products', $queryParams);

        $response = $this->handleResponse($response);

        return $this->formatProductsResponse($response);
    }

    /**
     * Format the products response to a simplified structure.
     *
     * @param array $response The raw API response
     * @return array Formatted products array
     */
    private function formatProductsResponse(array $response): array
    {
        if (!isset($response['data']) || !is_array($response['data'])) {
            return [];
        }

        return array_map(function ($item) {
            // Use sellPrice as the primary price
            $price = isset($item['sellPrice']) ? (float) $item['sellPrice'] : 0.0;

            // Build description from available fields
            $descriptionParts = [];

            if (!empty($item['productType'])) {
                $descriptionParts[] = "Type: {$item['productType']}";
            }

            if (!empty($item['currency'])) {
                $descriptionParts[] = "Currency: {$item['currency']}";
            }

            if (isset($item['inStock'])) {
                $stockStatus = $item['inStock'] ? 'In Stock' : 'Out of Stock';
                $descriptionParts[] = "Stock: {$stockStatus}";
            }

            if (!empty($item['categoryId'])) {
                $descriptionParts[] = "Category ID: {$item['categoryId']}";
            }

            $description = implode(' | ', $descriptionParts);

            return [
                'sku' => $item['id'] ?? null,
                'name' => $item['title'] ?? null,
                'description' => $description ?: null,
                'price' => $price,
            ];
        }, $response['data']);
    }
}
