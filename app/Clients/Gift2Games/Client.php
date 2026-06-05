<?php

namespace App\Clients\Gift2Games;

use App\Clients\BaseApiClient;

class Client extends BaseApiClient
{
    private string $configPrefixOverride;

    public function __construct(string $configPrefix)
    {
        $this->configPrefixOverride = $configPrefix;
        parent::__construct();
    }

    /**
     * Get the configuration key prefix for the service.
     */
    protected function getConfigPrefix(): string
    {
        return $this->configPrefixOverride;
    }

    /**
     * Get the service name for error messages.
     */
    protected function getServiceName(): string
    {
        return 'Gift2Games';
    }

    /**
     * Gift2Games doesn't use "Bearer" prefix in Authorization header.
     */
    protected function useBearerPrefix(): bool
    {
        return false;
    }

    /**
     * Override retry delay to not use a delay between retries.
     */
    protected function getRetryDelay(): int
    {
        return 0;
    }

    public function createOrder(array $orderData): array
    {
        $response = $this->getFormClient()->post('/create_order', $orderData);
        $response = $this->handleResponse($response);

        if (! $response['status']) {
            throw new \RuntimeException('Order creation failed: '.$response['error']['message']);
        }

        return $response;
    }

    public function getOrders(): array
    {
        $response = $this->getFormClient()->get('/orders');

        return $this->handleResponse($response);
    }

    public function fetchList(): array
    {
        $response = $this->getClient()->get('products');

        return $this->handleResponse($response);
    }

    public function checkBalance(): array
    {
        $response = $this->getClient()->get('check_balance');

        return $this->handleResponse($response);
    }
}
