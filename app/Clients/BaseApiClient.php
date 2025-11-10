<?php

namespace App\Clients;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;

abstract class BaseApiClient
{
    protected string $baseUrl;
    protected string $accessToken;
    protected ?string $apiKey = null;

    /**
     * Get the configuration key prefix for the service.
     * Example: 'services.ez_cards' or 'services.gift2games'
     */
    abstract protected function getConfigPrefix(): string;

    /**
     * Get the service name for error messages.
     * Example: 'EZ Cards' or 'Gift2Games'
     */
    abstract protected function getServiceName(): string;

    /**
     * Whether to use "Bearer" prefix in Authorization header.
     * Can be overridden by child classes.
     */
    protected function useBearerPrefix(): bool
    {
        return true;
    }

    public function __construct()
    {
        $configPrefix = $this->getConfigPrefix();
        $this->baseUrl = config("{$configPrefix}.base_url");
        $this->accessToken = config("{$configPrefix}.access_token");

        // Load API key if it exists in config
        if (config()->has("{$configPrefix}.api_key")) {
            $this->apiKey = config("{$configPrefix}.api_key");
        }
    }

    /**
     * Get headers for API requests.
     * Can be overridden by child classes if needed.
     */
    protected function getHeaders(): array
    {
        $authValue = $this->useBearerPrefix()
            ? 'Bearer ' . $this->accessToken
            : $this->accessToken;

        $headers = [
            'Authorization' => $authValue,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        // Add API key header if it exists
        if ($this->apiKey !== null) {
            $headers['x-api-key'] = $this->apiKey;
        }

        return $headers;
    }

    /**
     * Get the retry delay in milliseconds.
     * Can be overridden by child classes.
     */
    protected function getRetryDelay(): int
    {
        return 3000;
    }

    /**
     * Get the number of retry attempts.
     * Can be overridden by child classes.
     */
    protected function getRetryAttempts(): int
    {
        return 3;
    }

    /**
     * Get configured HTTP client with retry logic.
     */
    protected function getClient(): PendingRequest
    {
        return Http::withHeaders($this->getHeaders())
            ->retry($this->getRetryAttempts(), $this->getRetryDelay(), function ($exception) {
                // Don't retry for client errors (4xx), only for server errors (5xx) or connection issues
                return $exception instanceof RequestException && $exception->response->status() >= 500;
            })
            ->baseUrl($this->baseUrl);
    }

    /**
     * Get configured HTTP client with form-urlencoded content type.
     */
    protected function getFormClient(): PendingRequest
    {
        return Http::asForm()
            ->withHeaders(array_diff_key($this->getHeaders(), ['Content-Type' => '']))
            ->retry($this->getRetryAttempts(), $this->getRetryDelay(), function ($exception) {
                // Don't retry for client errors (4xx), only for server errors (5xx) or connection issues
                return $exception instanceof RequestException && $exception->response->status() >= 500;
            })
            ->baseUrl($this->baseUrl);
    }

    /**
     * Handle API response and extract data or throw exception.
     */
    protected function handleResponse(Response $response): array
    {
        if ($response->successful()) {
            return $response->json();
        }

        // Handle errors as needed, e.g., log them or throw exceptions
        throw new \Exception($this->getServiceName() . ' API request failed: ' . $response->body());
    }
}
