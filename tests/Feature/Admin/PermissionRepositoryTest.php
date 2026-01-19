<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\Permission;
use App\Repositories\PermissionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PermissionRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private PermissionRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PermissionRepository;
    }

    /**
     * Test getting paginated list of permissions
     */
    public function test_get_filtered_permissions_returns_paginated_results(): void
    {
        // Create 20 permissions
        Permission::factory()->count(20)->create();

        $result = $this->repository->getFilteredPermissions();

        $this->assertCount(15, $result->items()); // Default per_page is 15
        $this->assertEquals(20, $result->total());
        $this->assertEquals(1, $result->currentPage());
    }

    /**
     * Test filtering permissions by name and guard_name
     */
    public function test_get_filtered_permissions_with_filters(): void
    {
        Permission::factory()->withName('manage_users')->withGuardName('api')->create();
        Permission::factory()->withName('manage_posts')->withGuardName('api')->create();
        Permission::factory()->withName('delete_users')->withGuardName('web')->create();

        // Filter by name
        $result = $this->repository->getFilteredPermissions(['name' => 'manage']);
        $this->assertCount(2, $result->items());

        // Filter by guard_name
        $result = $this->repository->getFilteredPermissions(['guard_name' => 'web']);
        $this->assertCount(1, $result->items());
        $this->assertEquals('delete_users', $result->items()[0]->name);

        // Filter by both name and guard_name
        $result = $this->repository->getFilteredPermissions([
            'name' => 'manage',
            'guard_name' => 'api',
        ]);
        $this->assertCount(2, $result->items());
    }
}
