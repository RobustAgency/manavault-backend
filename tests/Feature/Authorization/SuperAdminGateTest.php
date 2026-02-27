<?php

namespace Tests\Feature\Authorization;

use Tests\TestCase;
use App\Models\User;
use App\Enums\UserRole;
use App\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SuperAdminGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Test that super_admin user bypasses all gates/permissions
     */
    public function test_super_admin_bypasses_all_gates(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        // Test with various arbitrary abilities
        $this->assertTrue(Gate::forUser($superAdmin)->allows('any-ability'));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('another-random-ability'));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('manage-users'));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('delete-products'));
    }

    /**
     * Test that super_admin bypasses even undefined gates
     */
    public function test_super_admin_bypasses_undefined_gates(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        // Test with an ability that was never explicitly defined
        $this->assertTrue(Gate::forUser($superAdmin)->allows('this-ability-does-not-exist'));
    }

    /**
     * Test that super_admin bypasses gate denies
     */
    public function test_super_admin_bypasses_gate_denies(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        // Even if we explicitly check denies, super_admin should bypass it
        $this->assertFalse(Gate::forUser($superAdmin)->denies('any-ability'));
    }

    /**
     * Test that non-super_admin users don't bypass gates
     */
    public function test_non_super_admin_users_do_not_bypass_gates(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN->value]);
        $user = User::factory()->create(['role' => UserRole::USER->value]);

        // Non-super admins should not bypass gates
        $this->assertFalse(Gate::forUser($admin)->allows('any-undefined-ability'));
        $this->assertFalse(Gate::forUser($user)->allows('any-undefined-ability'));
    }

    public function test_non_super_admin_with_spatie_permissions_do_not_bypass_gates(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER->value]);
        $role = Role::create(['name' => 'editor', 'guard_name' => 'supabase']);
        $permission = Permission::factory()->create(['name' => 'edit articles', 'guard_name' => 'supabase']);
        $role->givePermissionTo($permission);
        $user->assignRole($role);

        // Non-super admins with roles/permissions should not bypass gates
        $this->assertFalse(Gate::forUser($user)->allows('any-undefined-ability'));
    }

    /**
     * Test super_admin bypasses gate check called via middleware context
     */
    public function test_super_admin_bypasses_authorize_method(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        // This should not throw an exception
        $this->actingAs($superAdmin);
        $this->assertTrue(Gate::allows('any-random-ability'));
    }

    /**
     * Test super_admin returns true (not null) from gate before callback
     */
    public function test_super_admin_gate_before_returns_true(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);
        $regularUser = User::factory()->create(['role' => UserRole::USER->value]);

        // The Gate::before callback should return true for super_admin
        $result = Gate::forUser($superAdmin)->allows('any-ability');
        $this->assertTrue($result);

        // For regular user, it should return null (from Gate::before) and then proceed with normal authorization
        // which would deny access for non-existent abilities
        $result = Gate::forUser($regularUser)->allows('any-ability');
        $this->assertFalse($result);
    }

    /**
     * Test super_admin role is correctly set in database
     */
    public function test_super_admin_role_is_super_admin_value(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $this->assertEquals(UserRole::SUPER_ADMIN->value, $superAdmin->role);
    }

    /**
     * Test that super_admin can perform multiple different abilities in sequence
     */
    public function test_super_admin_can_perform_multiple_different_abilities(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN->value]);

        $abilities = [
            'create-product',
            'edit-product',
            'delete-product',
            'view-reports',
            'manage-users',
            'manage-roles',
            'manage-permissions',
            'access-settings',
        ];

        foreach ($abilities as $ability) {
            $this->assertTrue(
                Gate::forUser($superAdmin)->allows($ability),
                "Super admin should be able to {$ability}"
            );
        }
    }
}
