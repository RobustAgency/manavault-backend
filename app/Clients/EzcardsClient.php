<?php

namespace App\Clients;

class EzcardsClient extends BaseApiClient
{
    /**
     * Get the configuration key prefix for the service.
     */
    protected function getConfigPrefix(): string
    {
        return 'services.ez_cards';
    }

    /**
     * Get the service name for error messages.
     */
    protected function getServiceName(): string
    {
        return 'EZ Cards';
    }

    public function createOrder(array $orderData): array
    {
        $response = $this->getClient()->post('/v2/orders', $orderData);

        return $this->handleResponse($response);
    }

    /**
     * Fetch a list of products from the EZ Cards API.
     *
     * Query parameters:
     * - limit: Integer (1-1000, default: 1000) - Items per page
     * - page: Integer (default: 1) - Page number
     * - sku: String (optional) - Filter by SKU
     *
     * @param  array  $params  Query parameters
     * @return array The list of products
     *
     * @throws \Exception
     */
    public function getProducts(array $params = []): array
    {

        $response = $this->getClient()->get('/v2/products', $params);

        return $this->handleResponse($response);
    }

    public function getVoucherCodes(int $transactionID): array
    {
        $response = $this->getClient()->get('/v2/orders/'.$transactionID.'/codes');

        return $this->handleResponse($response);
    }
}
