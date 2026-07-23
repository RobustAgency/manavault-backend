<?php

namespace App\Clients\Irewardify;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;

class Client
{
    private const RETRY_ATTEMPTS = 3;

    private const RETRY_DELAY_MS = 3000;

    private const RATE_LIMIT_RETRY_DELAY_MS = 61000;

    public function getClient(): PendingRequest
    {
        return Http::baseUrl(config('services.irewardify.url'))
            ->retry(
                self::RETRY_ATTEMPTS,
                function (int $attempt, \Exception $exception) {
                    // Rate limited: honor Retry-After, otherwise wait out the one-minute window
                    if ($exception instanceof RequestException && $exception->response->status() === 429) {
                        $retryAfter = (int) $exception->response->header('Retry-After');

                        return $retryAfter > 0 ? $retryAfter * 1000 : self::RATE_LIMIT_RETRY_DELAY_MS;
                    }

                    return self::RETRY_DELAY_MS;
                },
                function ($exception) {
                    return $exception instanceof RequestException
                        && ($exception->response->status() === 429 || $exception->response->status() >= 500);
                },
                throw: false,
            );
    }

    public function getAccessToken(): string
    {
        return cache()->get('irewardify_access_token') ?? $this->authenticate();
    }

    public function authenticate(): string
    {
        $response = $this->getClient()->post('/customer/login', [
            'email' => config('services.irewardify.username'),
            'password' => config('services.irewardify.password'),
        ]);

        if ($response->successful()) {
            cache()->put('irewardify_access_token', $response->json('token'), now()->addMinutes(50));

            return $response->json('token');
        }

        throw new \Exception('Failed to authenticate with Irewardify: '.$response->body());
    }

    public function getWalletBalance(): array
    {
        $response = $this->getClient()
            ->withHeaders(['Authorization' => 'Bearer '.$this->getAccessToken()])
            ->get('/customer/wallet');

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Failed to fetch wallet balance from Irewardify: '.$response->body());
    }

    public function getProducts(): array
    {
        $response = $this->getClient()
            ->withHeaders(['Authorization' => 'Bearer '.$this->getAccessToken()])
            ->get('/customer/products', [
                'query' => [
                    'category' => 'Digital',
                ],
            ]);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Failed to fetch products from Irewardify: '.$response->body());
    }

    public function getProductDetails(string $productId): array
    {
        $response = $this->getClient()
            ->withHeaders(['Authorization' => 'Bearer '.$this->getAccessToken()])
            ->get("/customer/products/{$productId}");

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Failed to fetch product details from Irewardify: '.$response->body());
    }

    public function checkout(array $payload): array
    {
        $response = $this->getClient()
            ->withHeaders(['Authorization' => 'Bearer '.$this->getAccessToken()])
            ->post('/checkout', $payload);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Failed to checkout with Irewardify: '.$response->body());
    }

    public function getOrderDelivery(string $orderId): array
    {
        $response = $this->getClient()
            ->withHeaders(['Authorization' => 'Bearer '.$this->getAccessToken()])
            ->get("/order/delivery/{$orderId}");

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Failed to fetch order delivery from Irewardify: '.$response->body());
    }
}
