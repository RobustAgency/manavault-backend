<?php

namespace Tests\Feature\Controllers\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_super_admin_can_view_all_users_with_pagination(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);

        $users = User::factory()->count(5)->create(['role' => UserRole::USER]);

        foreach ($users as $user) {
            $user->created_at = now();
            $user->updated_at = now();
            $user->save();
        }
        $response = $this->actingAs($admin)->getJson('/api/admin/users?role=user');

        $response->assertOk();

        $responseData = $response->json();
        $this->assertFalse($responseData['error']);
        $this->assertEquals('Users retrieved successfully', $responseData['message']);
        $this->assertArrayHasKey('data', $responseData);

        foreach ($responseData['data']['data'] as $user) {
            $this->assertEquals(UserRole::USER->value, $user['role']);
        }
    }

    public function test_super_admin_can_view_user(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
        $user = User::factory()->create(['role' => UserRole::USER]);

        $response = $this->actingAs($admin)->getJson("/api/admin/users/{$user->id}");
        $response->assertOk();
        $response->assertJsonStructure([
            'error',
            'message',
            'data' => [
                'id',
                'name',
                'email',
                'created_at',
                'updated_at',
            ],
        ]);

        $responseData = $response->json();
        $this->assertFalse($responseData['error']);
        $this->assertEquals('User retrieved successfully', $responseData['message']);
        $this->assertArrayHasKey('data', $responseData);
    }
}
