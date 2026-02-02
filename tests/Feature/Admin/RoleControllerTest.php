<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Module;
use App\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RoleControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->admin = User::factory()->create(['role' => 'super_admin']);
    }

    /**
     * Test getting paginated list of roles
     */
    public function test_admin_can_get_paginated_roles(): void
    {
        $permission = Permission::factory()->create(['name' => 'manage users', 'guard_name' => 'supabase']);
        for ($i = 1; $i <= 20; $i++) {
            $role = Role::create(['name' => "role_{$i}", 'guard_name' => 'supabase']);
            $role->givePermissionTo([$permission->id]);
        }

        $response = $this->actingAs($this->admin)->getJson('/api/roles');

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
                'message' => 'Roles retrieved successfully.',
            ]);
    }

    /**
     * Test filtering roles by name
     */
    public function test_admin_can_filter_roles_by_name(): void
    {
        Role::create(['name' => 'admin', 'guard_name' => 'supabase']);
        Role::create(['name' => 'user', 'guard_name' => 'supabase']);
        Role::create(['name' => 'admin_super', 'guard_name' => 'supabase']);

        $response = $this->actingAs($this->admin)->getJson('/api/roles?name=admin');

        $response->assertStatus(200)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('error', false);

        $roles = $response->json('data.data');
        foreach ($roles as $role) {
            $this->assertStringContainsString('admin', $role['name']);
        }
    }

    /**
     * Test filtering roles by guard_name
     */
    public function test_admin_can_filter_roles_by_guard_name(): void
    {
        Role::create(['name' => 'api_admin', 'guard_name' => 'supabase']);
        Role::create(['name' => 'web_admin', 'guard_name' => 'web']);

        $response = $this->actingAs($this->admin)->getJson('/api/roles?guard_name=supabase');

        $response->assertStatus(200)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.guard_name', 'supabase');
    }

    /**
     * Test custom pagination
     */
    public function test_admin_can_paginate_roles_with_custom_per_page(): void
    {
        for ($i = 1; $i <= 50; $i++) {
            Role::create(['name' => "role_{$i}", 'guard_name' => 'supabase']);
        }

        $response = $this->actingAs($this->admin)->getJson('/api/roles?per_page=25');

        $response->assertStatus(200)
            ->assertJsonPath('data.per_page', 25)
            ->assertJsonPath('data.total', 50);
    }

    /**
     * Test storing a new role without permissions
     */
    public function test_admin_can_create_a_role(): void
    {
        $roleData = [
            'name' => 'new_role',
            'guard_name' => 'supabase',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/roles', $roleData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'error',
                'message',
                'data' => [
                    'id',
                    'name',
                    'guard_name',
                    'permissions',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJson([
                'error' => false,
                'message' => 'Role created successfully.',
            ]);

        $this->assertDatabaseHas('roles', ['name' => 'new_role']);
    }

    /**
     * Test creating role with permissions
     */
    public function test_admin_can_create_role_with_permissions(): void
    {
        $permissions = Permission::factory()->count(3)->create(['guard_name' => 'supabase']);
        $permissionIds = collect($permissions)->pluck('id')->toArray();

        $roleData = [
            'name' => 'admin_role',
            'guard_name' => 'supabase',
            'permission_ids' => $permissionIds,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/roles', $roleData);

        $response->assertStatus(201)
            ->assertJson([
                'error' => false,
                'message' => 'Role created successfully.',
            ]);

        $role = Role::where('name', 'admin_role')->first();
        $this->assertCount(3, $role->permissions);
    }

    /**
     * Test validation error when creating role without name
     */
    public function test_creation_fails_without_name(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/roles', ['guard_name' => 'supabase']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    /**
     * Test validation error for duplicate role name
     */
    public function test_creation_fails_with_duplicate_name(): void
    {
        Role::create(['name' => 'existing_role', 'guard_name' => 'supabase']);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/roles', [
                'name' => 'existing_role',
                'guard_name' => 'supabase',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    /**
     * Test validation error for invalid permission id
     */
    public function test_creation_fails_with_invalid_permission_id(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/roles', [
                'name' => 'invalid_perms_role',
                'guard_name' => 'supabase',
                'permission_ids' => [99999],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('permission_ids.0');
    }

    /**
     * Test showing a specific role
     */
    public function test_admin_can_show_a_role(): void
    {
        $role = Role::create(['name' => 'test_role', 'guard_name' => 'supabase']);
        $module = Module::factory()->create(['name' => 'Articles', 'slug' => 'articles']);
        $permission1 = Permission::factory()->create([
            'name' => 'edit articles',
            'guard_name' => 'supabase',
            'action' => 'edit',
            'module_id' => $module->id,
        ]);
        $permission2 = Permission::factory()->create([
            'name' => 'delete articles',
            'guard_name' => 'supabase',
            'action' => 'delete',
            'module_id' => $module->id,
        ]);
        $role->syncPermissions([$permission1, $permission2]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/roles/{$role->id}");

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Role retrieved successfully.',
                'data' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'guard_name' => $role->guard_name,
                ],
            ]);
        $this->assertCount(2, $response->json('data.permissions'));
        $this->assertEquals('edit', $response->json('data.permissions.0.action'));
        $this->assertEquals('delete', $response->json('data.permissions.1.action'));
    }

    /**
     * Test showing non-existent role returns 404
     */
    public function test_showing_non_existent_role_returns_404(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/roles/99999');

        $response->assertStatus(404);
    }

    /**
     * Test updating a role
     */
    public function test_admin_can_update_a_role(): void
    {
        $role = Role::create(['name' => 'original_role', 'guard_name' => 'supabase']);

        $updateData = [
            'name' => 'updated_role',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson("/api/roles/{$role->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Role updated successfully.',
                'data' => [
                    'name' => 'updated_role',
                ],
            ]);

        $this->assertDatabaseHas('roles', ['name' => 'updated_role']);
    }

    /**
     * Test syncing permissions on update
     */
    public function test_admin_can_sync_permissions_on_update(): void
    {
        $role = Role::create(['name' => 'test_role', 'guard_name' => 'supabase']);
        $oldPermissions = Permission::factory()->count(2)->create(['guard_name' => 'supabase']);
        $role->syncPermissions($oldPermissions);

        $newPermissions = Permission::factory()->count(3)->create(['guard_name' => 'supabase']);
        $newPermissionIds = collect($newPermissions)->pluck('id')->toArray();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/roles/{$role->id}", [
                'name' => $role->name,
                'permission_ids' => $newPermissionIds,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.permissions', function ($permissions) {
                return count($permissions) === 3;
            });
    }

    /**
     * Test update allows same name for same role
     */
    public function test_update_allows_same_name_for_same_role(): void
    {
        $role = Role::create(['name' => 'same_name', 'guard_name' => 'supabase']);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/roles/{$role->id}", [
                'name' => 'same_name',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'data' => [
                    'name' => 'same_name',
                ],
            ]);
    }

    /**
     * Test update fails with duplicate name from another role
     */
    public function test_update_fails_with_duplicate_name_from_another_role(): void
    {
        $role1 = Role::create(['name' => 'role_1', 'guard_name' => 'supabase']);
        $role2 = Role::create(['name' => 'role_2', 'guard_name' => 'supabase']);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/roles/{$role2->id}", [
                'name' => 'role_1',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    /**
     * Test deleting a role
     */
    public function test_admin_can_delete_a_role(): void
    {
        $role = Role::create(['name' => 'test_role', 'guard_name' => 'supabase']);
        $roleId = $role->id;

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/roles/{$role->id}");

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Role deleted successfully.',
            ]);

        $this->assertDatabaseMissing('roles', ['id' => $roleId]);
    }

    /**
     * Test deleting non-existent role returns 404
     */
    public function test_deleting_non_existent_role_returns_404(): void
    {
        $response = $this->actingAs($this->admin)
            ->deleteJson('/api/roles/99999');

        $response->assertStatus(404);
    }

    /**
     * Test JSON response structure
     */
    public function test_response_has_correct_json_structure(): void
    {
        $role = Role::create(['name' => 'test_role', 'guard_name' => 'supabase']);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/roles/{$role->id}");

        $response->assertJsonStructure([
            'error',
            'message',
            'data' => [
                'id',
                'name',
                'guard_name',
                'permissions',
                'created_at',
                'updated_at',
            ],
        ]);
    }

    /**
     * Test empty role list
     */
    public function test_empty_role_list_returns_correct_structure(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/roles');

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Roles retrieved successfully.',
                'data' => [
                    'total' => 0,
                ],
            ]);
    }
}
