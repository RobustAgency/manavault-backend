<?php

namespace App\Clients\Gift2Games;

use App\Clients\BaseApiClient;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;

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

    public function createOrders(array $orderData, int $count): array
    {
        $headers = array_diff_key($this->getHeaders(), ['Content-Type' => '']);
        $baseUrl = $this->baseUrl;

        $responses = Http::pool(function (Pool $pool) use ($orderData, $count, $headers, $baseUrl) {
            $requests = [];
            for ($i = 0; $i < $count; $i++) {
                $requests[] = $pool->asForm()
                    ->withHeaders($headers)
                    ->baseUrl($baseUrl)
                    ->post('/create_order', $orderData);
            }

            return $requests;
        });

        logger()->info('Gift2Games createOrders responses', ['responses' => $responses]);

        return array_map(function ($response) {
            if ($response instanceof \Throwable || $response->failed()) {
                return null;
            }

            $data = $response->json();

            if (! ($data['status'] ?? false)) {
                return null;
            }

            return $data;
        }, $responses);
    }

    public function getOrders(): array
    {
        $response = $this->getFormClient()->get('/orders');

        return $this->handleResponse($response);
    }

    public function getProducts(): array
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
