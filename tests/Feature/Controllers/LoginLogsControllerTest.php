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

    public function test_guest_user_cannot_access_login_logs(): void
    {
        LoginLog::factory()->count(5)->create();

        $response = $this->getJson('/api/login-logs');

        $response->assertUnauthorized();
    }

    public function test_unapproved_user_cannot_access_login_logs(): void
    {
        Notification::fake();
        $user = User::factory()->create(['role' => UserRole::USER, 'is_approved' => false]);

        $response = $this->actingAs($user)->getJson('/api/login-logs');

        $response->assertForbidden();
    }

    public function test_login_logs_cannot_be_created_over_http(): void
    {
        $response = $this->postJson('/api/login-logs', [
            'email' => 'spoofed@example.com',
            'ip_address' => '203.0.113.45',
            'activity' => 'login',
        ]);

        $response->assertStatus(405);
        $this->assertDatabaseMissing('login_logs', ['email' => 'spoofed@example.com']);
    }
}
