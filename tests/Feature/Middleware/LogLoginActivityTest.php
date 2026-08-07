<?php

namespace Tests\Feature\Middleware;

use Tests\TestCase;
use App\Models\LoginLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LogLoginActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_is_logged(): void
    {
        Http::fake([
            '*/auth/v1/token*' => Http::response([
                'access_token' => 'a-valid-access-token',
                'expires_at' => now()->addHour()->timestamp,
            ]),
        ]);

        $response = $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Testing)'])
            ->postJson('/api/auth/login', [
                'email' => 'user@example.com',
                'password' => 'secret-password',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('login_logs', [
            'email' => 'user@example.com',
            'activity' => 'login',
            'user_agent' => 'Mozilla/5.0 (Testing)',
        ]);

        $this->assertNotNull(LoginLog::first()->logged_in_at);
    }

    public function test_failed_login_is_logged_as_failed_login(): void
    {
        Http::fake([
            '*/auth/v1/token*' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'Invalid login credentials',
            ], 400),
        ]);

        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (Testing)'])
            ->postJson('/api/auth/login', [
                'email' => 'user@example.com',
                'password' => 'wrong-password',
            ]);

        $this->assertDatabaseHas('login_logs', [
            'email' => 'user@example.com',
            'activity' => 'failed_login',
        ]);
    }

    public function test_the_logged_ip_address_and_email_come_from_the_request_not_the_payload(): void
    {
        Http::fake([
            '*/auth/v1/token*' => Http::response([
                'access_token' => 'a-valid-access-token',
                'expires_at' => now()->addHour()->timestamp,
            ]),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'secret-password',
            'ip_address' => '1.2.3.4',
            'activity' => 'spoofed',
        ]);

        $log = LoginLog::sole();

        $this->assertSame('user@example.com', $log->email);
        $this->assertSame('login', $log->activity);
        $this->assertNotSame('1.2.3.4', $log->ip_address);
    }

    public function test_login_attempt_without_an_email_is_not_logged(): void
    {
        $response = $this->postJson('/api/auth/login', ['password' => 'secret-password']);

        $response->assertStatus(422);
        $this->assertDatabaseCount('login_logs', 0);
    }
}
