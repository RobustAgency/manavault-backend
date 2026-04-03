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

    public function authenticate(): string
    {
        $timestamp = time();

        $login = config('services.giftery.login');
        $password = config('services.giftery.password');

        $signature = $this->generateAuthSignature($timestamp);

        $response = $this->getClient()->withHeaders([
            'time' => (string) $timestamp,
            'signature' => $signature,
        ])->post('/auth', [
            'login' => $login,
            'password' => $password,
        ]);

        $data = $response->json();

        if (($data['statusCode'] ?? null) !== 0) {
            throw new \Exception($data['message'] ?? 'Giftery auth failed');
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
        ])->post("/api/v2/auth/refresh/{$refreshToken}");

        $data = $response->json();

        if (($data['statusCode'] ?? null) !== 0) {
            return $this->authenticate();
        }

        $accessToken = $data['data']['accessToken'];
        $newRefreshToken = $data['data']['refreshToken'];

        cache()->put('giftery_access_token', $accessToken, now()->addMinutes(50));
        cache()->put('giftery_refresh_token', $newRefreshToken, now()->addDays(7));

        return $accessToken;
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
}
