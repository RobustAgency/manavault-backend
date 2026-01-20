<?php

namespace Tests\Unit\Repositories;

use Tests\TestCase;
use App\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Repositories\RoleRepository;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RoleRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected RoleRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->repository = new RoleRepository;
    }

    public function test_it_can_create_a_role_and_assign_to_a_group()
    {
        $permission = Permission::factory()->create(['name' => 'edit_articles', 'guard_name' => 'supabase']);
        $data = [
            'name' => 'Editor',
            'permission_ids' => [$permission->id],
            'guard_name' => 'supabase',
        ];
        $role = $this->repository->createRole($data);

        $this->assertInstanceOf(Role::class, $role);
        $this->assertEquals('Editor', $role->name);

        $this->assertTrue($role->hasPermissionTo('edit_articles'));

        $this->assertDatabaseHas('roles', [
            'name' => 'Editor',
        ]);
    }

    public function test_it_can_update_role_permissions()
    {
        $role = $this->repository->createRole(['name' => 'Admin', 'guard_name' => 'supabase']);
        $permission = Permission::factory()->create(['name' => 'delete_user', 'guard_name' => 'supabase']);

        $this->repository->updateRole($role, [
            'name' => 'SuperAdmin',
            'permission_ids' => [$permission->id],
        ]);

        $this->assertEquals('SuperAdmin', $role->fresh()->name);
        $this->assertTrue($role->hasPermissionTo('delete_user'));
    }

    public function test_it_can_delete_a_role()
    {
        $role = $this->repository->createRole(['name' => 'Ghost', 'guard_name' => 'supabase']);

        $this->repository->deleteRole($role);

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }
}
