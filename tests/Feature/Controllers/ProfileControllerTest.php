<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRole;
use App\Models\Permission;
use Tests\Fakes\FakeSupabase;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*/auth/v1/admin/users' => function ($request) {
                $requestData = $request->data();

                return Http::response(FakeSupabase::getUserCreationResponse([
                    'email' => $requestData['email'],
                    'name' => $requestData['user_metadata']['name'] ?? 'Test User',
                    'email_verified' => $requestData['email_confirm'] ?? true,
                ]), 200);
            },
        ]);

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_user_can_view_profile(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'id' => 1,
            'role' => UserRole::USER,
            'is_approved' => true,
        ]);

        $response = $this->actingAs($user)->getJson('/api/profile');

        $response->assertOk();
        $response->assertJsonStructure([
            'error',
            'message',
            'data' => [
                'user' => [
                    'id',
                    'name',
                    'email',
                    'email_verified_at',
                    'is_approved',
                    'created_at',
                    'updated_at',
                ],
                'has_payment_method',
            ],
        ]);

        $responseData = $response->json();
        $this->assertFalse($responseData['error']);
        $this->assertEquals('Profile retrieved successfully.', $responseData['message']);
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('user', $responseData['data']);
        $this->assertArrayHasKey('has_payment_method', $responseData['data']);
        $this->assertEquals($user->id, $responseData['data']['user']['id']);
        $this->assertEquals($user->email, $responseData['data']['user']['email']);
        $this->assertEquals($user->name, $responseData['data']['user']['name']);
    }

    public function test_non_approved_user_cannot_access_profile(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'id' => 1,
            'role' => UserRole::USER,
            'is_approved' => false,
        ]);

        $response = $this->actingAs($user)->getJson('/api/profile');
        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Your account is not approved yet. Please contact support.',
        ]);
    }

    public function test_user_can_view_info(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'id' => 1,
            'role' => UserRole::USER,
            'is_approved' => true,
        ]);

        $response = $this->actingAs($user)->getJson('/api/profile/user-info');

        $response->assertOk();
        $response->assertJsonStructure([
            'error',
            'message',
            'data' => [
                'id',
                'name',
                'email',
                'is_approved',
                'created_at',
                'updated_at',
            ],
        ]);

        $responseData = $response->json();
        $this->assertFalse($responseData['error']);
        $this->assertEquals('User info retrieved successfully.', $responseData['message']);
        $this->assertEquals($user->id, $responseData['data']['id']);
        $this->assertEquals($user->email, $responseData['data']['email']);
        $this->assertEquals($user->name, $responseData['data']['name']);
    }

    public function test_user_can_view_own_permissions_including_roles(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'id' => 1,
            'role' => UserRole::USER,
            'is_approved' => true,
        ]);

        // Create role and permissions
        $role = Role::create(['name' => 'editor', 'guard_name' => 'supabase']);
        $permission1 = Permission::factory()->create(['name' => 'view_user', 'guard_name' => 'supabase']);
        $permission2 = Permission::factory()->create(['name' => 'create_user', 'guard_name' => 'supabase']);

        // Assign permissions to role
        $role->givePermissionTo([$permission1, $permission2]);

        // Assign role to user
        $user->assignRole($role);

        $response = $this->actingAs($user)->getJson('/api/profile/user-info');

        $response->assertOk();
        $responseData = $response->json();
        $this->assertFalse($responseData['error']);
        $this->assertEquals($user->id, $responseData['data']['id']);
        $this->assertEquals($user->email, $responseData['data']['email']);
        $this->assertEquals('editor', $responseData['data']['role']);
        $this->assertIsArray($responseData['data']['modules']);
    }

    public function test_non_approved_user_cannot_access_user_info(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'id' => 1,
            'role' => UserRole::USER,
            'is_approved' => false,
        ]);

        $response = $this->actingAs($user)->getJson('/api/profile/user-info');
        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Your account is not approved yet. Please contact support.',
        ]);
    }

    public function test_unauthenticated_user_cannot_access_user_info(): void
    {
        Notification::fake();

        $response = $this->getJson('/api/profile/user-info');
        $response->assertUnauthorized();
    }
}
