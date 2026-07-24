<?php

namespace App\Clients\Gamezcode;

use Illuminate\Http\Client\Response;
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
            })
            ->baseUrl($this->getBaseUrl());
    }

    /**
     * Handle API response and extract data or throw exception.
     */
    protected function handleResponse(Response $response): array
    {
        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Gamezcode API request failed: '.$response->body());
    }

    /**
     * Fetch the product catalog.
     */
    public function getProducts(int $take = 20, int $skip = 0): array
    {
        $response = $this->getClient()->get('/catalog/products', [
            'take' => $take,
            'skip' => $skip,
        ]);

        return $this->handleResponse($response);
    }

    /**
     * Fetch a single product's details by its product code.
     */
    public function getProduct(string $productCode): array
    {
        $response = $this->getClient()->get("/catalog/products/{$productCode}");

        return $this->handleResponse($response);
    }

    /**
     * Place an order.
     */
    public function placeOrder(array $orderData): array
    {
        $response = $this->getClient()->post('/orders', $orderData);

        return $this->handleResponse($response);
    }

    /**
     * Retrieve an order by its reference.
     */
    public function getOrder(string $reference): array
    {
        $response = $this->getClient()->get("/orders/{$reference}");

        return $this->handleResponse($response);
    }
}
