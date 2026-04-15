<?php

namespace App\Clients\Gamezcode;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;

class Client
{
    private const MAX_RETRIES = 3;

    private const RETRY_DELAY_MS = 1000;

    private const PAGE_SIZE = 100;

    private function getClient(?string $accessToken = null): PendingRequest
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($accessToken !== null) {
            $headers['Authorization'] = 'Bearer '.$accessToken;
        }

        return Http::withHeaders($headers)
            ->retry(self::MAX_RETRIES, self::RETRY_DELAY_MS, function ($exception) {
                if ($exception instanceof RequestException) {
                    return $exception->response->serverError();
                }

                return $exception instanceof \Illuminate\Http\Client\ConnectionException;
            })
            ->baseUrl(config('services.gamezcode.base_url'));
    }

    /**
     * Authenticate with email/password and cache both tokens.
     */
    public function authenticate(): string
    {
        $response = $this->getClient()->post('/auth/login', [
            'email' => config('services.gamezcode.email'),
            'password' => config('services.gamezcode.password'),
        ]);

        $data = $response->json();

        if (! isset($data['accessToken']) || ! isset($data['refreshToken'])) {
            throw new \Exception('Gamezcode auth failed - No tokens in response: '.json_encode($data));
        }

        $accessToken = $data['accessToken'];
        $refreshToken = $data['refreshToken'];

        cache()->put('gamezcode_access_token', $accessToken, now()->addMinutes(50));
        cache()->put('gamezcode_refresh_token', $refreshToken, now()->addDays(7));

        return $accessToken;
    }

    /**
     * Use the cached refresh token to obtain a new access token.
     * Falls back to full re-authentication if the refresh token is missing or expired.
     */
    public function refreshToken(): string
    {
        $refreshToken = cache()->get('gamezcode_refresh_token');

        if (! $refreshToken) {
            return $this->authenticate();
        }

        $response = $this->getClient($refreshToken)->post('/auth/refresh');

        if ($response->status() === 401) {
            return $this->authenticate();
        }

        $data = $response->json();

        if (! isset($data['accessToken'])) {
            return $this->authenticate();
        }

        $accessToken = $data['accessToken'];
        $newRefreshToken = $data['refreshToken'] ?? $refreshToken;

        cache()->put('gamezcode_access_token', $accessToken, now()->addMinutes(50));
        cache()->put('gamezcode_refresh_token', $newRefreshToken, now()->addDays(7));

        return $accessToken;
    }

    /**
     * Resolve a valid access token: use cached token or refresh if missing.
     */
    public function getAccessToken(): string
    {
        return cache()->get('gamezcode_access_token') ?? $this->refreshToken();
    }

    /**
     * Perform an authenticated GET request, auto-refreshing on 401.
     */
    private function authenticatedGet(string $endpoint, array $query = []): array
    {
        $token = $this->getAccessToken();
        $response = $this->getClient($token)->get($endpoint, $query);

        if ($response->status() === 401) {
            $token = $this->authenticate();
            $response = $this->getClient($token)->get($endpoint, $query);
        }

        return $response->json();
    }

    /**
     * Perform an authenticated POST request, auto-refreshing on 401.
     */
    private function authenticatedPost(string $endpoint, array $payload = []): array
    {
        $token = $this->getAccessToken();
        $response = $this->getClient($token)->post($endpoint, $payload);

        if ($response->status() === 401) {
            $token = $this->authenticate();
            $response = $this->getClient($token)->post($endpoint, $payload);
        }

        return $response->json();
    }

    /**
     * Fetch a single page of products from the catalog.
     *
     * @param  int  $skip  Number of products to skip (offset)
     * @param  int  $take  Number of products to return (max 100)
     */
    public function getProducts(int $skip = 0, int $take = self::PAGE_SIZE): array
    {
        return $this->authenticatedGet('/catalog/products', [
            'skip' => $skip,
            'take' => $take,
        ]);
    }

    /**
     * Fetch ALL products by paginating through the full catalog.
     *
     * @return array Flat list of all product objects
     */
    public function getAllProducts(): array
    {
        $allProducts = [];
        $skip = 0;
        $take = self::PAGE_SIZE;

        do {
            $response = $this->getProducts($skip, $take);
            $total = $response['count'] ?? 0;
            $products = $response['products'] ?? [];

            $allProducts = array_merge($allProducts, $products);
            $skip += $take;
        } while ($skip < $total && ! empty($products));

        return $allProducts;
    }

    /**
     * Fetch details for a single product by its EAN code.
     */
    public function getProductByEan(string $ean): array
    {
        return $this->authenticatedGet('/catalog/product', ['ean' => $ean]);
    }

    /**
     * Place an order with Kalixo/Gamezcode.
     *
     * @param  string  $externalOrderCode  Your unique internal order identifier
     * @param  int  $price  Total order price in smallest currency unit (e.g. pence)
     * @param  string  $currency  Currency code (e.g. 'GBP')
     * @param  array  $orderProducts  Array of product line items
     */
    public function placeOrder(
        string $externalOrderCode,
        int $price,
        string $currency,
        array $orderProducts
    ): array {
        return $this->authenticatedPost('/orders/place-order', [
            'externalOrderCode' => $externalOrderCode,
            'price' => $price,
            'currency' => $currency,
            'orderProducts' => $orderProducts,
        ]);
    }

    /**
     * Retrieve a previously placed order by its Kalixo orderId.
     */
    public function getOrder(string $orderId): array
    {
        return $this->authenticatedGet('/orders/retrieve-order', ['id' => $orderId]);
    }
}
