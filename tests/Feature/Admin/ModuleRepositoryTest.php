<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\Module;
use App\Models\Permission;
use App\Repositories\ModuleRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ModuleRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private ModuleRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ModuleRepository;
    }

    /**
     * Test getting paginated list of modules with permissions
     */
    public function test_get_filtered_modules_returns_paginated_results(): void
    {
        // Create 20 modules with permissions
        for ($i = 1; $i <= 20; $i++) {
            $module = Module::factory()->create(['name' => "module_{$i}"]);
            Permission::factory()->count(2)->create(['module_id' => $module->id]);
        }

        $result = $this->repository->getFilteredModules();

        $this->assertCount(15, $result->items()); // Default per_page is 15
        $this->assertEquals(20, $result->total());
        $this->assertEquals(1, $result->currentPage());
    }

    /**
     * Test filtering modules by name
     */
    public function test_get_filtered_modules_by_name(): void
    {
        Module::factory()->create(['name' => 'user_management', 'slug' => 'user-management']);
        Module::factory()->create(['name' => 'product_management', 'slug' => 'product-management']);
        Module::factory()->create(['name' => 'user_analytics', 'slug' => 'user-analytics']);

        $result = $this->repository->getFilteredModules(['name' => 'user']);

        $this->assertCount(2, $result->items());
        $this->assertEquals(2, $result->total());

        foreach ($result->items() as $module) {
            $this->assertStringContainsString('user', $module->name);
        }
    }

    /**
     * Test filtering modules by slug
     */
    public function test_get_filtered_modules_by_slug(): void
    {
        Module::factory()->create(['name' => 'User Management', 'slug' => 'user-management']);
        Module::factory()->create(['name' => 'Product Management', 'slug' => 'product-management']);
        Module::factory()->create(['name' => 'User Analytics', 'slug' => 'user-analytics']);

        $result = $this->repository->getFilteredModules(['slug' => 'user-management']);

        $this->assertCount(1, $result->items());
        $this->assertEquals('user-management', $result->items()[0]->slug);
    }

    /**
     * Test custom pagination per_page
     */
    public function test_get_filtered_modules_with_custom_per_page(): void
    {
        for ($i = 1; $i <= 50; $i++) {
            Module::factory()->create();
        }

        $result = $this->repository->getFilteredModules(['per_page' => 25]);

        $this->assertCount(25, $result->items());
        $this->assertEquals(25, $result->perPage());
        $this->assertEquals(50, $result->total());
    }

    /**
     * Test modules are loaded with their permissions
     */
    public function test_get_filtered_modules_eager_loads_permissions(): void
    {
        $module = Module::factory()->create();
        Permission::factory()->create(['name' => 'create_module', 'module_id' => $module->id]);
        Permission::factory()->create(['name' => 'edit_module', 'module_id' => $module->id]);

        $result = $this->repository->getFilteredModules();

        $this->assertCount(1, $result->items());
        $loadedModule = $result->items()[0];

        // Check that permissions relationship is loaded
        $this->assertTrue($loadedModule->relationLoaded('permissions'));
        $this->assertCount(2, $loadedModule->permissions);
    }

    /**
     * Test filtering by multiple criteria
     */
    public function test_get_filtered_modules_with_multiple_filters(): void
    {
        Module::factory()->create(['name' => 'user_management', 'slug' => 'user-management']);
        Module::factory()->create(['name' => 'user_analytics', 'slug' => 'user-analytics']);
        Module::factory()->create(['name' => 'product_management', 'slug' => 'product-management']);

        $result = $this->repository->getFilteredModules([
            'name' => 'user',
            'slug' => 'user-management',
        ]);

        $this->assertCount(1, $result->items());
        $this->assertEquals('user-management', $result->items()[0]->slug);
    }

    /**
     * Test empty result set
     */
    public function test_get_filtered_modules_returns_empty_when_no_match(): void
    {
        Module::factory()->count(5)->create();

        $result = $this->repository->getFilteredModules(['name' => 'nonexistent']);

        $this->assertCount(0, $result->items());
        $this->assertEquals(0, $result->total());
    }

    /**
     * Test modules are ordered by created_at descending
     */
    public function test_get_filtered_modules_orders_by_created_at_desc(): void
    {
        $module1 = Module::factory()->create(['name' => 'first']);
        sleep(1); // Ensure different timestamps
        $module2 = Module::factory()->create(['name' => 'second']);

        $result = $this->repository->getFilteredModules();

        $modules = $result->items();
        $this->assertEquals($module2->id, $modules[0]->id);
        $this->assertEquals($module1->id, $modules[1]->id);
    }
}
