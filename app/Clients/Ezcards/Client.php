<?php

namespace App\Clients\Ezcards;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;

class Client
{
    /**
     * @var string
     * The base URL for the EZ Cards API.
     */
    private string $baseUrl;

    /** 
     * @var string
     * The API key for authenticating requests.
     */
    private string $apiKey;

    /**
     * @var string
     * The access token for authenticating requests.
     */
    private string $accessToken;

    public function __construct()
    {
        $this->baseUrl = config('services.ez_cards.base_url');
        $this->apiKey = config('services.ez_cards.api_key');
        $this->accessToken = config('services.ez_cards.access_token');
    }

    private function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->accessToken,
            'x-api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    protected function getClient(): PendingRequest
    {
        return Http::withHeaders($this->getHeaders())
            ->retry(3, 3000, function ($exception) {
                // Don't retry for client errors (4xx), only for server errors (5xx) or connection issues
                return $exception instanceof RequestException && $exception->response->status() >= 500;
            })
            ->baseUrl($this->baseUrl);
    }

    protected function handleResponse(Response $response): array
    {
        if ($response->successful()) {
            return $response->json();
        }

        // Handle errors as needed, e.g., log them or throw exceptions
        throw new \Exception('EZ Cards API request failed: ' . $response->body());
    }
}
