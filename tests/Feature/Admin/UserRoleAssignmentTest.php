<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserRoleAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'super_admin']);
    }

    /**
     * Test admin can assign roles to a user
     */
    public function test_admin_can_assign_roles_to_user(): void
    {
        $user = User::factory()->create();
        $role1 = Role::create(['name' => 'editor', 'guard_name' => 'supabase']);
        $role2 = Role::create(['name' => 'moderator', 'guard_name' => 'supabase']);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$user->id}/assign-roles", [
                'role_ids' => [$role1->id, $role2->id],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Roles assigned successfully',
            ])
            ->assertJsonStructure([
                'error',
                'message',
                'data' => [
                    'id',
                    'name',
                    'email',
                ],
            ]);

        $user->refresh();
        $this->assertCount(2, $user->roles);
        $this->assertTrue($user->hasRole($role1));
        $this->assertTrue($user->hasRole($role2));
    }

    /**
     * Test admin can assign single role to user
     */
    public function test_admin_can_assign_single_role_to_user(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'viewer', 'guard_name' => 'supabase']);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$user->id}/assign-roles", [
                'role_ids' => [$role->id],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Roles assigned successfully',
            ]);

        $user->refresh();
        $this->assertCount(1, $user->roles);
        $this->assertTrue($user->hasRole($role));
    }

    /**
     * Test assigning roles replaces previous roles
     */
    public function test_assigning_roles_replaces_previous_roles(): void
    {
        $user = User::factory()->create();
        $oldRole = Role::create(['name' => 'old_role', 'guard_name' => 'web']);
        $newRole = Role::create(['name' => 'new_role', 'guard_name' => 'supabase']);

        // Assign old role first
        $user->syncRoles([$oldRole->id]);
        $this->assertTrue($user->hasRole($oldRole));

        // Assign new role
        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$user->id}/assign-roles", [
                'role_ids' => [$newRole->id],
            ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertCount(1, $user->roles);
        $this->assertFalse($user->hasRole($oldRole));
        $this->assertTrue($user->hasRole($newRole));
    }

    /**
     * Test validation fails without role_ids
     */
    public function test_validation_fails_without_role_ids(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$user->id}/assign-roles", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('role_ids');
    }

    /**
     * Test validation fails with invalid role id
     */
    public function test_validation_fails_with_invalid_role_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/users/{$user->id}/assign-roles", [
                'role_ids' => [99999],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('role_ids.0');
    }
}
