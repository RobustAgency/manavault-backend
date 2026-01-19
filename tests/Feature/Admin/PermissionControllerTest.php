<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PermissionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    /**
     * Test getting paginated list of permissions
     */
    public function test_admin_can_get_paginated_permissions(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            Permission::factory()->withName("permission_{$i}")->create();
        }

        $response = $this->actingAs($this->admin)->getJson('/api/admin/permissions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'guard_name',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'current_page',
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
            ])
            ->assertJson([
                'error' => false,
                'message' => 'Permissions retrieved successfully.',
            ]);
    }

    /**
     * Test filtering permissions by name
     */
    public function test_admin_can_filter_permissions_by_name(): void
    {
        Permission::factory()->withName('manage_users')->create();
        Permission::factory()->withName('manage_posts')->create();
        Permission::factory()->withName('delete_comments')->create();

        $response = $this->actingAs($this->admin)->getJson('/api/admin/permissions?name=manage');

        $response->assertStatus(200)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('error', false);

        $permissions = $response->json('data.data');
        foreach ($permissions as $permission) {
            $this->assertStringContainsString('manage', $permission['name']);
        }
    }

    /**
     * Test filtering permissions by guard_name
     */
    public function test_admin_can_filter_permissions_by_guard_name(): void
    {
        Permission::factory()->withGuardName('api')->create();
        Permission::factory()->withGuardName('api')->create();
        Permission::factory()->withGuardName('web')->create();

        $response = $this->actingAs($this->admin)->getJson('/api/admin/permissions?guard_name=api');

        $response->assertStatus(200)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.data.0.guard_name', 'api');
    }

    /**
     * Test custom pagination
     */
    public function test_admin_can_paginate_permissions_with_custom_per_page(): void
    {
        for ($i = 1; $i <= 50; $i++) {
            Permission::factory()->create();
        }

        $response = $this->actingAs($this->admin)->getJson('/api/admin/permissions?per_page=25');

        $response->assertStatus(200)
            ->assertJsonPath('data.per_page', 25)
            ->assertJsonPath('data.total', 50);
    }

    /**
     * Test empty permissions list returns correct structure
     */
    public function test_empty_permissions_list_returns_correct_structure(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/admin/permissions');

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Permissions retrieved successfully.',
                'data' => [
                    'total' => 0,
                ],
            ]);
    }
}
