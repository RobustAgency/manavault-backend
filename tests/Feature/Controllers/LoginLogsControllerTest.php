<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRole;
use App\Models\LoginLog;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LoginLogsControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_can_retrieve_all_login_logs_with_pagination(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        LoginLog::factory()->count(20)->create();

        $response = $this->actingAs($admin)->getJson('/api/login-logs');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'current_page',
                'data',
                'first_page_url',
                'from',
                'last_page',
                'last_page_url',
                'links',
                'next_page_url',
                'path',
                'per_page',
                'prev_page_url',
                'to',
                'total',
            ],
            'message',
            'error',
        ]);

        $responseData = $response->json();
        $this->assertFalse($responseData['error']);
        $this->assertEquals('Login logs retrieved successfully.', $responseData['message']);
        $this->assertEquals(20, $responseData['data']['total']);
    }

    public function test_can_filter_login_logs_by_email(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $targetEmail = 'test@example.com';
        LoginLog::factory()->forEmail($targetEmail)->count(3)->create();
        LoginLog::factory()->count(5)->create();

        $response = $this->actingAs($admin)->getJson("/api/login-logs?email={$targetEmail}");

        $response->assertOk();
        $responseData = $response->json();
        $this->assertEquals(3, $responseData['data']['total']);

        foreach ($responseData['data']['data'] as $log) {
            $this->assertEquals($targetEmail, $log['email']);
        }
    }

    public function test_can_filter_login_logs_by_ip_address(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $targetIp = '192.168.1.100';
        LoginLog::factory()->fromIp($targetIp)->count(4)->create();
        LoginLog::factory()->count(6)->create();

        $response = $this->actingAs($admin)->getJson("/api/login-logs?ip_address={$targetIp}");

        $response->assertOk();
        $responseData = $response->json();
        $this->assertEquals(4, $responseData['data']['total']);

        foreach ($responseData['data']['data'] as $log) {
            $this->assertEquals($targetIp, $log['ip_address']);
        }
    }

    public function test_can_set_custom_per_page_limit(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        LoginLog::factory()->count(30)->create();

        $response = $this->actingAs($admin)->getJson('/api/login-logs?per_page=10');

        $response->assertOk();
        $responseData = $response->json();
        $this->assertEquals(10, $responseData['data']['per_page']);
        $this->assertEquals(30, $responseData['data']['total']);
        $this->assertCount(10, $responseData['data']['data']);
    }

    public function test_can_combine_multiple_filters(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $targetEmail = 'admin@example.com';
        $targetIp = '10.0.0.1';

        LoginLog::factory()->forEmail($targetEmail)->fromIp($targetIp)->count(2)->create(['activity' => 'login']);
        LoginLog::factory()->forEmail($targetEmail)->count(3)->create();
        LoginLog::factory()->fromIp($targetIp)->count(4)->create();
        LoginLog::factory()->count(5)->create();

        $response = $this->actingAs($admin)->getJson(
            "/api/login-logs?email={$targetEmail}&ip_address={$targetIp}&activity=login"
        );

        $response->assertOk();
        $responseData = $response->json();
        $this->assertEquals(2, $responseData['data']['total']);
    }

    public function test_validates_email_format(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $response = $this->actingAs($admin)->getJson('/api/login-logs?email=invalid-email');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_validates_per_page_is_positive_integer(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $response = $this->actingAs($admin)->getJson('/api/login-logs?per_page=-5');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['per_page']);
    }

    public function test_validates_per_page_maximum_value(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $response = $this->actingAs($admin)->getJson('/api/login-logs?per_page=150');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['per_page']);
    }

    public function test_can_create_login_log(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $loginLogData = [
            'email' => 'newuser@example.com',
            'ip_address' => '203.0.113.45',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'activity' => 'login',
            'logged_in_at' => now()->toDateTimeString(),
        ];

        $response = $this->actingAs($admin)->postJson('/api/login-logs', $loginLogData);

        $response->assertOk();
        $response->assertJsonStructure([
            'message',
            'error',
        ]);

        $responseData = $response->json();
        $this->assertFalse($responseData['error']);
        $this->assertEquals('Login log created successfully.', $responseData['message']);

        $this->assertDatabaseHas('login_logs', [
            'email' => 'newuser@example.com',
            'ip_address' => '203.0.113.45',
            'activity' => 'login',
        ]);
    }

    public function test_create_login_log_requires_email(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $loginLogData = [
            'ip_address' => '203.0.113.45',
            'activity' => 'login',
        ];

        $response = $this->actingAs($admin)->postJson('/api/login-logs', $loginLogData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_create_login_log_requires_valid_email(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $loginLogData = [
            'email' => 'not-an-email',
            'ip_address' => '203.0.113.45',
            'activity' => 'login',
        ];

        $response = $this->actingAs($admin)->postJson('/api/login-logs', $loginLogData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_create_login_log_requires_ip_address(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $loginLogData = [
            'email' => 'user@example.com',
            'activity' => 'login',
        ];

        $response = $this->actingAs($admin)->postJson('/api/login-logs', $loginLogData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ip_address']);
    }

    public function test_create_login_log_requires_valid_ip_address(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $loginLogData = [
            'email' => 'user@example.com',
            'ip_address' => 'not-an-ip',
            'activity' => 'login',
        ];

        $response = $this->actingAs($admin)->postJson('/api/login-logs', $loginLogData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ip_address']);
    }

    public function test_create_login_log_requires_activity(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $loginLogData = [
            'email' => 'user@example.com',
            'ip_address' => '203.0.113.45',
        ];

        $response = $this->actingAs($admin)->postJson('/api/login-logs', $loginLogData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['activity']);
    }

    public function test_create_login_log_validates_logged_out_at_after_logged_in_at(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $loggedInAt = now();
        $loggedOutAt = now()->subHour(); // Before logged_in_at

        $loginLogData = [
            'email' => 'user@example.com',
            'ip_address' => '203.0.113.45',
            'activity' => 'logout',
            'logged_in_at' => $loggedInAt->toDateTimeString(),
            'logged_out_at' => $loggedOutAt->toDateTimeString(),
        ];

        $response = $this->actingAs($admin)->postJson('/api/login-logs', $loginLogData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['logged_out_at']);
    }

    public function test_create_login_log_with_complete_data(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $loggedInAt = now()->subHour();
        $loggedOutAt = now();

        $loginLogData = [
            'email' => 'complete@example.com',
            'ip_address' => '198.51.100.42',
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
            'activity' => 'logout',
            'logged_in_at' => $loggedInAt->toDateTimeString(),
            'logged_out_at' => $loggedOutAt->toDateTimeString(),
        ];

        $response = $this->actingAs($admin)->postJson('/api/login-logs', $loginLogData);

        $response->assertOk();

        $this->assertDatabaseHas('login_logs', [
            'email' => 'complete@example.com',
            'ip_address' => '198.51.100.42',
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
            'activity' => 'logout',
        ]);
    }

    public function test_guest_user_can_access_login_logs(): void
    {
        LoginLog::factory()->count(5)->create();

        $response = $this->getJson('/api/login-logs');

        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'message',
            'error',
        ]);
    }

    public function test_guest_user_can_create_login_logs(): void
    {
        $loginLogData = [
            'email' => 'guest@example.com',
            'ip_address' => '203.0.113.45',
            'activity' => 'login',
        ];

        $response = $this->postJson('/api/login-logs', $loginLogData);

        $response->assertOk();
        $this->assertDatabaseHas('login_logs', [
            'email' => 'guest@example.com',
            'ip_address' => '203.0.113.45',
            'activity' => 'login',
        ]);
    }
}
