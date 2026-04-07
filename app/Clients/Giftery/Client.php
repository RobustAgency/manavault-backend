<?php

namespace App\Clients\Giftery;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

class Client
{
    protected static function secret(): string
    {
        return base64_decode(config('services.giftery.secret'));
    }

    private function getClient(): PendingRequest
    {
        return Http::baseUrl(config('services.giftery.base_url'));
    }

    public static function generateAuthSignature(int $time): string
    {
        $message = $time.config('services.giftery.login').config('services.giftery.password');

        return base64_encode(hash_hmac('sha256', $message, self::secret(), true));
    }

    public static function generateRefreshTokenSignature(int $time, string $refreshToken): string
    {
        $message = $time.$refreshToken;

        return base64_encode(hash_hmac('sha256', $message, self::secret(), true));
    }

    public static function generateRequestSignature(int $time): string
    {
        return base64_encode(hash_hmac('sha256', (string) $time, self::secret(), true));
    }

    public function generateReserveSignature(int $time, int $itemId, array $fields): string
    {
        // Convert to key=value
        $formatted = array_map(function ($field) {
            return $field['key'].'='.$field['value'];
        }, $fields);

        // Sort alphabetically
        sort($formatted);

        // Join with commas
        $fieldsString = implode(',', $formatted);

        $message = $time.$itemId.$fieldsString;

        return base64_encode(hash_hmac(
            'sha256',
            $message,
            self::secret(),
            true
        ));
    }

    public function generateConfirmSignature(int $time, string $uuid): string
    {
        $message = $time.$uuid;

        return base64_encode(hash_hmac(
            'sha256',
            $message,
            base64_decode(config('services.giftery.secret')),
            true
        ));
    }

    public function authenticate(): string
    {
        $timestamp = time();

        $login = config('services.giftery.login');
        $password = config('services.giftery.password');

        $signature = $this->generateAuthSignature($timestamp);

        $payload = [
            'login' => $login,
            'password' => $password,
        ];

        $response = $this->getClient()->withHeaders([
            'time' => (string) $timestamp,
            'signature' => $signature,
        ])->post('/auth', $payload);

        $data = $response->json();

        if (($data['statusCode'] ?? null) == -101) {
            throw new \Exception($data['message'] ?? 'Giftery auth failed - Bad credentials');
        }

        if (! isset($data['data']['accessToken']) || ! isset($data['data']['refreshToken'])) {
            throw new \Exception('Giftery auth failed - No tokens in response: '.json_encode($data));
        }

        $accessToken = $data['data']['accessToken'];
        $refreshToken = $data['data']['refreshToken'];

        cache()->put('giftery_access_token', $accessToken, now()->addMinutes(50));
        cache()->put('giftery_refresh_token', $refreshToken, now()->addDays(7));

        return $accessToken;
    }

    public function refreshToken(): string
    {
        $refreshToken = cache()->get('giftery_refresh_token');

        if (! $refreshToken) {
            return $this->authenticate();
        }

        $timestamp = time();

        $response = $this->getClient()->withHeaders([
            'time' => $timestamp,
            'signature' => $this->generateRefreshTokenSignature($timestamp, $refreshToken),
        ])->post("/auth/refresh/{$refreshToken}");

        $data = $response->json();

        if ($data['statusCode'] == -103 || $data['statusCode'] == -105) {
            return $this->authenticate();
        }

        $accessToken = $data['data']['accessToken'];
        $newRefreshToken = $data['data']['refreshToken'];

        cache()->put('giftery_access_token', $accessToken, now()->addMinutes(50));
        cache()->put('giftery_refresh_token', $newRefreshToken, now()->addDays(7));

        return $accessToken;
    }

    public function getAccount(): array
    {
        $time = time();
        $response = $this->getClient()->withHeaders([
            'time' => (string) $time,
            'signature' => $this->generateRequestSignature($time),
            'Authorization' => "Bearer {$this->refreshToken()}",
        ])->get('/accounts');

        return $response->json();
    }

    public function getProducts(): array
    {
        $response = $this->getClient()->withHeaders([
            'time' => (string) time(),
            'signature' => $this->generateRequestSignature(time()),
            'Authorization' => "Bearer {$this->refreshToken()}",
        ])->get('/products', [
            'responseType' => 'full',
        ]);

        return $response->json();
    }

    public function getProductDetails(int $productId): array
    {
        $response = $this->getClient()->withHeaders([
            'time' => (string) time(),
            'signature' => $this->generateRequestSignature(time()),
            'Authorization' => "Bearer {$this->refreshToken()}",
        ])->get("/products/{$productId}");

        return $response->json();
    }

    public function reserveOrder(array $payload): array
    {
        $timestamp = time();

        $signature = $this->generateReserveSignature(
            $timestamp,
            $payload['itemId'],
            $payload['fields']
        );

        $reservePayload = array_merge($payload, [
            'clientTime' => $payload['clientTime'] ?? now()->toIso8601String(),
        ]);

        return $this->getClient()
            ->withHeaders([
                'time' => $timestamp,
                'signature' => $signature,
                'Authorization' => "Bearer {$this->refreshToken()}",
            ])
            ->post('/operations/reserve', $reservePayload)->json();
    }

    public function confirmOrder(string $transactionUUID): array
    {
        $timestamp = time();

        $signature = $this->generateConfirmSignature($timestamp, $transactionUUID);

        $response = $this->getClient()
            ->withHeaders([
                'time' => (string) $timestamp,
                'signature' => $signature,
                'Authorization' => "Bearer {$this->refreshToken()}",
            ])
            ->post("/operations/{$transactionUUID}/confirm");

        $data = $response->json();

        if (($data['statusCode'] ?? null) === -102) {
            $this->refreshToken();

            return $this->confirmOrder($transactionUUID);
        }

        if (($data['statusCode'] ?? 0) !== 0) {
            throw new \Exception($data['message'] ?? 'Confirm failed');
        }

        return $data['data'];
    }

    public function getOperation(string $transactionUUID): array
    {
        $timestamp = time();

        $signature = $this->generateRequestSignature($timestamp);

        $response = $this->getClient()
            ->withHeaders([
                'time' => (string) $timestamp,
                'signature' => $signature,
                'Authorization' => "Bearer {$this->refreshToken()}",
            ])
            ->get("/operations/{$transactionUUID}");

        $data = $response->json();

        if (($data['statusCode'] ?? null) === -102) {
            $this->refreshToken();

            return $this->getOperation($transactionUUID);
        }

        if (($data['statusCode'] ?? 0) !== 0) {
            throw new \Exception($data['message'] ?? 'Get operation failed');
        }

        return $data['data'];
    }
}
