<?php

namespace App\Clients\Gamezcode;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;

class Client
{
    private const RETRY_ATTEMPTS = 3;

    private const RETRY_DELAY_MS = 1000;

    protected function getBaseUrl(): string
    {
        return config('services.gamezcode.base_url');
    }

    protected function getApiKey(): string
    {
        return config('services.gamezcode.api_key');
    }

    /**
     * Get an HTTP client configured for the Gamezcode (Kalixo) API.
     *
     * Retries on 5xx server errors only.
     */
    protected function getClient(): PendingRequest
    {
        return Http::withHeaders([
            'Accept' => 'application/json',
            'x-api-key' => $this->getApiKey(),
        ])
            ->retry(self::RETRY_ATTEMPTS, self::RETRY_DELAY_MS, function ($exception) {
                return $exception instanceof RequestException && $exception->response->status() >= 500;
            }, throw: false)
            ->baseUrl($this->getBaseUrl());
    }

    /**
     * Fetch the product catalog.
     */
    public function getProducts(int $take = 20, int $skip = 0): array
    {
        return $this->getClient()->get('/catalog/products', [
            'take' => $take,
            'skip' => $skip,
        ])->throw()->json();
    }

    /**
     * Fetch a single product's details by its product code.
     */
    public function getProduct(string $productCode): array
    {
        return $this->getClient()->get("/catalog/products/{$productCode}")->throw()->json();
    }

    /**
     * Place an order.
     */
    public function placeOrder(array $orderData): array
    {
        return $this->getClient()->post('/orders', $orderData)->throw()->json();
    }

    /**
     * Retrieve an order by its reference (the placement orderId or your externalOrderCode).
     * Returns null if no order matches the reference.
     */
    public function getOrder(string $reference): ?array
    {
        $response = $this->getClient()->get("/orders/{$reference}");

        if ($response->status() === 404) {
            return null;
        }

        return $response->throw()->json();
    }
}
