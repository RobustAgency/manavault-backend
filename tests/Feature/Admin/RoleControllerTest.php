<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Group;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RoleControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    /**
     * Test getting paginated list of roles
     */
    public function test_admin_can_get_paginated_roles(): void
    {
        $group = Group::create(['name' => 'Test Group', 'description' => 'Test Description']);
        $permission = Permission::create(['name' => 'manage users', 'guard_name' => 'api']);
        for ($i = 1; $i <= 20; $i++) {
            $role = Role::create(['name' => "role_{$i}", 'guard_name' => 'api', 'group_id' => $group->id]);
            $role->givePermissionTo([$permission->id]);
        }

        $response = $this->actingAs($this->admin)->getJson('/api/admin/roles');

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
                            'group_id',
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
        Role::create(['name' => 'admin', 'guard_name' => 'api']);
        Role::create(['name' => 'user', 'guard_name' => 'api']);
        Role::create(['name' => 'admin_super', 'guard_name' => 'api']);

        $response = $this->actingAs($this->admin)->getJson('/api/admin/roles?name=admin');

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
        $group = Group::create(['name' => 'Test Group', 'description' => 'Test Description']);
        Role::create(['name' => 'api_admin', 'guard_name' => 'api']);
        Role::create(['name' => 'web_admin', 'guard_name' => 'web']);

        $response = $this->actingAs($this->admin)->getJson('/api/admin/roles?guard_name=api');

        $response->assertStatus(200)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.guard_name', 'api');
    }

    /**
     * Test custom pagination
     */
    public function test_admin_can_paginate_roles_with_custom_per_page(): void
    {
        for ($i = 1; $i <= 50; $i++) {
            Role::create(['name' => "role_{$i}", 'guard_name' => 'api']);
        }

        $response = $this->actingAs($this->admin)->getJson('/api/admin/roles?per_page=25');

        $response->assertStatus(200)
            ->assertJsonPath('data.per_page', 25)
            ->assertJsonPath('data.total', 50);
    }

    /**
     * Test storing a new role without permissions
     */
    public function test_admin_can_create_a_role(): void
    {
        $group = Group::create(['name' => 'Default Group', 'description' => 'Default Description']);
        $roleData = [
            'name' => 'new_role',
            'guard_name' => 'api',
            'group_id' => $group->id,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/roles', $roleData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'error',
                'message',
                'data' => [
                    'id',
                    'name',
                    'guard_name',
                    'group_id',
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
        $group = Group::create(['name' => 'Test Group', 'description' => 'Test Description']);
        $permissions = [];
        for ($i = 1; $i <= 3; $i++) {
            $permissions[] = Permission::create(['name' => "permission_{$i}", 'guard_name' => 'api']);
        }
        $permissionIds = collect($permissions)->pluck('id')->toArray();

        $roleData = [
            'name' => 'admin_role',
            'guard_name' => 'api',
            'permission_ids' => $permissionIds,
            'group_id' => $group->id,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/roles', $roleData);

        $response->assertStatus(201)
            ->assertJson([
                'error' => false,
                'message' => 'Role created successfully.',
            ]);

        $role = Role::where('name', 'admin_role')->first();
        $this->assertCount(3, $role->permissions);
    }

    /**
     * Test creating role with group
     */
    public function test_admin_can_create_role_with_group(): void
    {
        $group = Group::create(['name' => 'Test Group', 'description' => 'Test Description']);

        $roleData = [
            'name' => 'grouped_role',
            'guard_name' => 'api',
            'group_id' => $group->id,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/roles', $roleData);

        $response->assertStatus(201)
            ->assertJsonPath('data.group_id', $group->id)
            ->assertDatabaseHas('roles', ['name' => 'grouped_role', 'group_id' => $group->id]);
    }

    /**
     * Test creating role with permissions and group
     */
    public function test_admin_can_create_role_with_permissions_and_group(): void
    {
        $group = Group::create(['name' => 'Test Group', 'description' => 'Test Description']);
        $permissions = [];
        for ($i = 1; $i <= 2; $i++) {
            $permissions[] = Permission::create(['name' => "permission_{$i}", 'guard_name' => 'api']);
        }
        $permissionIds = collect($permissions)->pluck('id')->toArray();

        $roleData = [
            'name' => 'complete_role',
            'guard_name' => 'api',
            'permission_ids' => $permissionIds,
            'group_id' => $group->id,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/roles', $roleData);

        $response->assertStatus(201)
            ->assertJsonPath('data.group_id', $group->id);

        $role = Role::where('name', 'complete_role')->first();
        $this->assertCount(2, $role->permissions);
    }

    /**
     * Test validation error when creating role without name
     */
    public function test_creation_fails_without_name(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/roles', ['guard_name' => 'api']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    /**
     * Test validation error for duplicate role name
     */
    public function test_creation_fails_with_duplicate_name(): void
    {
        Role::create(['name' => 'existing_role', 'guard_name' => 'api']);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/roles', [
                'name' => 'existing_role',
                'guard_name' => 'api',
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
            ->postJson('/api/admin/roles', [
                'name' => 'invalid_perms_role',
                'guard_name' => 'api',
                'permission_ids' => [99999],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('permission_ids.0');
    }

    /**
     * Test validation error for invalid group id
     */
    public function test_creation_fails_with_invalid_group_id(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/roles', [
                'name' => 'invalid_group_role',
                'guard_name' => 'api',
                'group_id' => 99999,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('group_id');
    }

    /**
     * Test showing a specific role
     */
    public function test_admin_can_show_a_role(): void
    {
        $role = Role::create(['name' => 'test_role', 'guard_name' => 'api']);
        $permissions = [];
        for ($i = 1; $i <= 2; $i++) {
            $permissions[] = Permission::create(['name' => "permission_{$i}", 'guard_name' => 'api']);
        }
        $role->syncPermissions($permissions);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/admin/roles/{$role->id}");

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Role retrieved successfully.',
                'data' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'guard_name' => $role->guard_name,
                ],
            ])
            ->assertJsonPath('data.permissions', collect($permissions)->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
            ])->toArray());
    }

    /**
     * Test showing non-existent role returns 404
     */
    public function test_showing_non_existent_role_returns_404(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/roles/99999');

        $response->assertStatus(404);
    }

    /**
     * Test updating a role
     */
    public function test_admin_can_update_a_role(): void
    {
        $group = Group::create(['name' => 'Test Group', 'description' => 'Test Description']);
        $role = Role::create(['name' => 'original_role', 'guard_name' => 'api', 'group_id' => $group->id]);

        $updateData = [
            'name' => 'updated_role',
        ];

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/roles/{$role->id}", $updateData);

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
        $role = Role::create(['name' => 'test_role', 'guard_name' => 'api']);
        $oldPermissions = [];
        for ($i = 1; $i <= 2; $i++) {
            $oldPermissions[] = Permission::create(['name' => "old_permission_{$i}", 'guard_name' => 'api']);
        }
        $role->syncPermissions($oldPermissions);

        $newPermissions = [];
        for ($i = 1; $i <= 3; $i++) {
            $newPermissions[] = Permission::create(['name' => "new_permission_{$i}", 'guard_name' => 'api']);
        }
        $newPermissionIds = collect($newPermissions)->pluck('id')->toArray();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/roles/{$role->id}", [
                'name' => $role->name,
                'permission_ids' => $newPermissionIds,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.permissions', function ($permissions) {
                return count($permissions) === 3;
            });
    }

    /**
     * Test updating role group
     */
    public function test_admin_can_update_role_group(): void
    {
        $oldGroup = Group::create(['name' => 'Old Group', 'description' => 'Old Description']);
        $newGroup = Group::create(['name' => 'New Group', 'description' => 'New Description']);
        $role = Role::create(['name' => 'test_role', 'group_id' => $oldGroup->id, 'guard_name' => 'api']);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/roles/{$role->id}", [
                'name' => $role->name,
                'group_id' => $newGroup->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.group_id', $newGroup->id);

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'group_id' => $newGroup->id]);
    }

    /**
     * Test update allows same name for same role
     */
    public function test_update_allows_same_name_for_same_role(): void
    {
        $role = Role::create(['name' => 'same_name', 'guard_name' => 'api']);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/roles/{$role->id}", [
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
        $role1 = Role::create(['name' => 'role_1', 'guard_name' => 'api']);
        $role2 = Role::create(['name' => 'role_2', 'guard_name' => 'api']);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/roles/{$role2->id}", [
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
        $group = Group::create(['name' => 'Test Group', 'description' => 'Test Description']);
        $role = Role::create(['name' => 'test_role', 'guard_name' => 'api', 'group_id' => $group->id]);
        $roleId = $role->id;

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/admin/roles/{$role->id}");

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
            ->deleteJson('/api/admin/roles/99999');

        $response->assertStatus(404);
    }

    /**
     * Test JSON response structure
     */
    public function test_response_has_correct_json_structure(): void
    {
        $role = Role::create(['name' => 'test_role', 'guard_name' => 'api']);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/admin/roles/{$role->id}");

        $response->assertJsonStructure([
            'error',
            'message',
            'data' => [
                'id',
                'name',
                'guard_name',
                'group_id',
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
        $response = $this->actingAs($this->admin)->getJson('/api/admin/roles');

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Roles retrieved successfully.',
                'data' => [
                    'total' => 0,
                ],
            ]);
    }

    /**
     * Test default guard_name is api
     */
    public function test_default_guard_name_is_api(): void
    {
        $group = Group::create(['name' => 'Test Group', 'description' => 'Test Description']);
        $roleData = [
            'name' => 'default_guard_role',
            'group_id' => $group->id,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/roles', $roleData);

        $response->assertStatus(201)
            ->assertJsonPath('data.guard_name', 'api');
    }
}
