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

        return $this->handleResponse($response);
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
