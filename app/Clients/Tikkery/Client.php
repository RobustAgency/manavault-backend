<?php

namespace App\Clients\Tikkery;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;

class Client
{
    private const RETRY_ATTEMPTS = 3;

    private const RETRY_DELAY_MS = 1000;

    /**
     * Access token TTL in minutes (slightly less than the 3600s expires_in).
     */
    private const ACCESS_TOKEN_TTL_MINUTES = 55;

    /**
     * Refresh token TTL in days.
     */
    private const REFRESH_TOKEN_TTL_DAYS = 30;

    protected function getApiBaseUrl(): string
    {
        return config('services.tikkery.base_url');
    }

    protected function getAuthBaseUrl(): string
    {
        return config('services.tikkery.auth_url');
    }

    protected function getClientId(): string
    {
        return config('services.tikkery.client_id');
    }

    protected function getClientSecret(): string
    {
        return config('services.tikkery.client_secret');
    }

    protected function getUsername(): string
    {
        return config('services.tikkery.username');
    }

    protected function getPassword(): string
    {
        return config('services.tikkery.password');
    }

    protected function getScopes(): string
    {
        return config('services.tikkery.scopes', 'balance.read products.read orders.read orders.create');
    }

    /**
     * Get a plain HTTP client with retry logic (no base URL).
     */
    private function getBaseClient(): PendingRequest
    {
        return Http::retry(self::RETRY_ATTEMPTS, self::RETRY_DELAY_MS, function ($exception) {
            return $exception instanceof RequestException && $exception->response->status() >= 500;
        });
    }

    /**
     * Authenticate using the OAuth2 password grant flow and cache the tokens.
     */
    public function authenticate(): string
    {
        $response = $this->getBaseClient()
            ->asForm()
            ->post($this->getAuthBaseUrl(), [
                'grant_type' => 'password',
                'client_id' => $this->getClientId(),
                'client_secret' => $this->getClientSecret(),
                'scope' => $this->getScopes(),
                'username' => $this->getUsername(),
                'password' => $this->getPassword(),
            ]);

        if (! $response->successful()) {
            throw new \Exception('Tikkery authentication failed: '.$response->body());
        }

        $data = $response->json();

        $accessToken = $data['access_token'];
        $refreshToken = $data['refresh_token'] ?? null;

        cache()->put(
            'tikkery_access_token',
            $accessToken,
            now()->addMinutes(self::ACCESS_TOKEN_TTL_MINUTES)
        );

        if ($refreshToken) {
            cache()->put(
                'tikkery_refresh_token',
                $refreshToken,
                now()->addDays(self::REFRESH_TOKEN_TTL_DAYS)
            );
        }

        return $accessToken;
    }

    /**
     * Use the refresh token to get a new access token.
     * Falls back to full re-authentication if no refresh token is cached.
     */
    public function refreshToken(): string
    {
        $refreshToken = cache()->get('tikkery_refresh_token');

        if (! $refreshToken) {
            return $this->authenticate();
        }

        $response = $this->getBaseClient()
            ->asForm()
            ->post($this->getAuthBaseUrl(), [
                'grant_type' => 'refresh_token',
                'client_id' => $this->getClientId(),
                'client_secret' => $this->getClientSecret(),
                'refresh_token' => $refreshToken,
            ]);

        if (! $response->successful()) {
            // Refresh token may be expired; fall back to full auth
            return $this->authenticate();
        }

        $data = $response->json();

        if (empty($data['access_token'])) {
            return $this->authenticate();
        }

        $accessToken = $data['access_token'];
        $newRefreshToken = $data['refresh_token'] ?? $refreshToken;

        cache()->put(
            'tikkery_access_token',
            $accessToken,
            now()->addMinutes(self::ACCESS_TOKEN_TTL_MINUTES)
        );

        cache()->put(
            'tikkery_refresh_token',
            $newRefreshToken,
            now()->addDays(self::REFRESH_TOKEN_TTL_DAYS)
        );

        return $accessToken;
    }

    /**
     * Get a valid access token, refreshing or re-authenticating as needed.
     */
    public function getAccessToken(): string
    {
        $cached = cache()->get('tikkery_access_token');

        if ($cached) {
            return $cached;
        }

        return $this->refreshToken();
    }

    /**
     * Get an HTTP client configured for the Tikkery API with a valid Bearer token.
     */
    protected function getClient(): PendingRequest
    {
        return Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->getAccessToken(),
        ])
            ->retry(self::RETRY_ATTEMPTS, self::RETRY_DELAY_MS, function ($exception) {
                return $exception instanceof RequestException && $exception->response->status() >= 500;
            })
            ->baseUrl($this->getApiBaseUrl());
    }

    /**
     * Handle API response and extract data or throw exception.
     */
    protected function handleResponse(\Illuminate\Http\Client\Response $response): array
    {
        if ($response->successful()) {
            $data = $response->json();

            return is_array($data) ? $data : ['data' => $data];
        }

        throw new \Exception('Tikkery API request failed: '.$response->body());
    }

    /**
     * Get the account balance at the given date.
     */
    public function getBalance(string $date): array
    {
        $response = $this->getClient()->get('/balance', ['date' => $date]);

        if ($response->successful()) {
            $data = $response->json();

            if (! is_array($data)) {
                return ['balance' => $data];
            }

            return $data;
        }

        throw new \Exception('Tikkery API request failed: '.$response->body());
    }

    /**
     * List available products from Tikkery.
     */
    public function listProducts(int $limit = 100, int $offset = 0): array
    {
        $response = $this->getClient()->get('/products', [
            'limit' => $limit,
            'offset' => $offset,
        ]);

        return $this->handleResponse($response);
    }

    /**
     * Get stock availability for one or more SKUs.
     */
    public function getStock(array $skus): array
    {
        // Build query string manually since Guzzle won't repeat the same key
        $queryString = implode('&', array_map(
            fn (string $sku) => 'sku[]='.urlencode($sku),
            $skus
        ));

        $response = $this->getClient()->get('/products/stock?'.$queryString);

        return $this->handleResponse($response);
    }

    /**
     * List previously created orders.
     */
    public function listOrders(int $limit = 100, int $offset = 0): array
    {
        $response = $this->getClient()->get('/orders', [
            'limit' => $limit,
            'offset' => $offset,
        ]);

        return $this->handleResponse($response);
    }

    /**
     * Get details of a single order by its order number.
     */
    public function getOrder(string $orderNumber): array
    {
        $response = $this->getClient()->get("/orders/{$orderNumber}");

        return $this->handleResponse($response);
    }

    /**
     * Create a new order. Tikkery automatically pays it using the account balance.
     */
    public function createOrder(array $orderData): array
    {
        $response = $this->getClient()->post('/orders/create', $orderData);

        return $this->handleResponse($response);
    }
}
