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
     * Test admin can assign role to a user
     */
    public function test_admin_can_assign_role_to_user(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'editor', 'guard_name' => 'supabase']);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/users/{$user->id}/assign-roles", [
                'role_id' => $role->id,
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
        $this->assertTrue($user->hasRole($role));
    }

    /**
     * Test assigning role replaces previous role
     */
    public function test_assigning_role_replaces_previous_role(): void
    {
        $user = User::factory()->create();
        $oldRole = Role::create(['name' => 'old_role', 'guard_name' => 'web']);
        $newRole = Role::create(['name' => 'new_role', 'guard_name' => 'supabase']);

        // Assign old role first
        $user->syncRoles([$oldRole->id]);
        $this->assertTrue($user->hasRole($oldRole));

        // Assign new role
        $response = $this->actingAs($this->admin)
            ->postJson("/api/users/{$user->id}/assign-roles", [
                'role_id' => $newRole->id,
            ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertCount(1, $user->roles);
        $this->assertFalse($user->hasRole($oldRole));
        $this->assertTrue($user->hasRole($newRole));
    }

    /**
     * Test validation fails without role_id
     */
    public function test_validation_fails_without_role_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/users/{$user->id}/assign-roles", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('role_id');
    }
}
