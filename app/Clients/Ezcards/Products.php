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
    public function fetchList(array $params = []): array
    {
        if (!empty($params)) {
            $params = $this->validateQueryParams($params);
        }

        $response = $this->getClient()->get('/v2/products', $params);

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
        if (!isset($response['data']['items']) || !is_array($response['data']['items'])) {
            return [];
        }

        return array_map(function ($item) {
            // Get the first price from the prices array
            $price = isset($item['prices'][0]['price']) ? (float) $item['prices'][0]['price'] : 0.0;

            // Build description from available fields
            $descriptionParts = [];

            if (!empty($item['brand'])) {
                $descriptionParts[] = "Brand: {$item['brand']}";
            }

            if (!empty($item['faceValue'])) {
                $descriptionParts[] = "Face Value: {$item['currency']} {$item['faceValue']}";
            }

            if (!empty($item['percentageOffFaceValue'])) {
                $descriptionParts[] = "Discount: {$item['percentageOffFaceValue']}%";
            }

            if (!empty($item['country'])) {
                $descriptionParts[] = "Country: {$item['country']}";
            }

            if (!empty($item['format'])) {
                $format = $item['format'] === 'D' ? 'Digital' : $item['format'];
                $descriptionParts[] = "Format: {$format}";
            }

            $description = implode(' | ', $descriptionParts);

            return [
                'sku' => $item['sku'] ?? null,
                'name' => $item['name'] ?? null,
                'description' => $description ?: null,
                'price' => $price,
            ];
        }, $response['data']['items']);
    }

    private function validateQueryParams(array $params): array
    {
        $validated = [];

        if (isset($params['limit'])) {
            $limit = (int) $params['limit'];
            if ($limit < 1 || $limit > 1000) {
                throw new \InvalidArgumentException('The "limit" parameter must be between 1 and 1000.');
            }
            $validated['limit'] = $limit;
        }

        if (isset($params['page'])) {
            $page = (int) $params['page'];
            if ($page < 1) {
                throw new \InvalidArgumentException('The "page" parameter must be a positive integer.');
            }
            $validated['page'] = $page;
        }

        if (isset($params['sku'])) {
            $validated['sku'] = (string) $params['sku'];
        }

        return $validated;
    }
}
