<?php

namespace Tests\Unit\Repositories;

use Tests\TestCase;
use App\Models\Group;
use Spatie\Permission\Models\Role;
use App\Repositories\RoleRepository;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RoleRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected RoleRepository $repository;

    protected Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Enable teams in the config for the test environment
        config(['permission.teams' => true]);

        // 2. Reset the Spatie cache
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->repository = new RoleRepository;

        // 3. Create a default group to work with
        $this->group = Group::create(['name' => 'Test Group']);
    }

    public function test_it_can_create_a_role_and_assign_to_a_group()
    {
        // Arrange
        $permission = Permission::create(['name' => 'edit_articles', 'guard_name' => 'api']);
        $data = [
            'name' => 'Editor',
            'group_id' => $this->group->id,
            'permission_ids' => [$permission->id],
        ];

        // Act
        $role = $this->repository->createRole($data);

        // Assert
        $this->assertInstanceOf(Role::class, $role);
        $this->assertEquals('Editor', $role->name);
        $this->assertEquals($this->group->id, $role->group_id);

        // Check if permission was synced
        $this->assertTrue($role->hasPermissionTo('edit_articles'));

        $this->assertDatabaseHas('roles', [
            'name' => 'Editor',
            'group_id' => $this->group->id,
        ]);
    }

    public function test_it_filters_roles_by_group_id()
    {
        // Arrange: Create another group and a role in it
        $otherGroup = Group::create(['name' => 'Other Group']);

        // Role in Group A
        $this->repository->createRole(['name' => 'Manager', 'group_id' => $this->group->id]);

        // Role in Group B
        $this->repository->createRole(['name' => 'Viewer', 'group_id' => $otherGroup->id]);

        // Act: Filter for Group A
        $results = $this->repository->getFilteredRoles(['group_id' => $this->group->id]);

        // Assert
        $this->assertCount(1, $results);
        $this->assertEquals('Manager', $results->first()->name);
    }

    public function test_it_can_update_role_permissions()
    {
        // Arrange
        $role = $this->repository->createRole(['name' => 'Admin', 'group_id' => $this->group->id]);
        $permission = Permission::create(['name' => 'delete_user', 'guard_name' => 'api']);

        // Act
        $this->repository->updateRole($role, [
            'name' => 'SuperAdmin',
            'permission_ids' => [$permission->id],
        ]);

        // Assert
        $this->assertEquals('SuperAdmin', $role->fresh()->name);
        $this->assertTrue($role->hasPermissionTo('delete_user'));
    }

    public function test_it_can_delete_a_role()
    {
        // Arrange
        $role = $this->repository->createRole(['name' => 'Ghost', 'group_id' => $this->group->id]);

        // Act
        $this->repository->deleteRole($role);

        // Assert
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }
}
