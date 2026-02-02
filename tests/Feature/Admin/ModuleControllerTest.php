<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Module;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ModuleControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'super_admin']);
    }

    /**
     * Test admin can get paginated list of modules with permissions
     */
    public function test_admin_can_get_paginated_modules_with_permissions(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $module = Module::factory()->create(['name' => "module_{$i}"]);
            Permission::factory()->count(3)->create(['module_id' => $module->id]);
        }

        $response = $this->actingAs($this->admin)->getJson('/api/modules');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'slug',
                            'permissions',
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
                'message' => 'Modules retrieved successfully.',
            ]);
    }

    /**
     * Test filtering modules by name
     */
    public function test_admin_can_filter_modules_by_name(): void
    {
        Module::factory()->create(['name' => 'user_management', 'slug' => 'user-management']);
        Module::factory()->create(['name' => 'product_management', 'slug' => 'product-management']);
        Module::factory()->create(['name' => 'user_analytics', 'slug' => 'user-analytics']);

        $response = $this->actingAs($this->admin)->getJson('/api/modules?name=user');

        $response->assertStatus(200)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('error', false);

        $modules = $response->json('data.data');
        foreach ($modules as $module) {
            $this->assertStringContainsString('user', $module['name']);
        }
    }

    /**
     * Test filtering modules by slug
     */
    public function test_admin_can_filter_modules_by_slug(): void
    {
        Module::factory()->create(['name' => 'User Management', 'slug' => 'user-management']);
        Module::factory()->create(['name' => 'Product Management', 'slug' => 'product-management']);

        $response = $this->actingAs($this->admin)->getJson('/api/modules?slug=user-management');

        $response->assertStatus(200)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.slug', 'user-management');
    }

    /**
     * Test custom pagination
     */
    public function test_admin_can_paginate_modules_with_custom_per_page(): void
    {
        for ($i = 1; $i <= 50; $i++) {
            Module::factory()->create();
        }

        $response = $this->actingAs($this->admin)->getJson('/api/modules?per_page=25');

        $response->assertStatus(200)
            ->assertJsonPath('data.per_page', 25)
            ->assertJsonPath('data.total', 50);
    }

    /**
     * Test empty modules list returns correct structure
     */
    public function test_empty_modules_list_returns_correct_structure(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/modules');

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Modules retrieved successfully.',
                'data' => [
                    'total' => 0,
                ],
            ]);
    }

    /**
     * Test modules include their permissions
     */
    public function test_modules_include_their_permissions(): void
    {
        $module = Module::factory()->create();
        Permission::factory()->create(['name' => 'create_module', 'module_id' => $module->id]);
        Permission::factory()->create(['name' => 'edit_module', 'module_id' => $module->id]);

        $response = $this->actingAs($this->admin)->getJson('/api/modules');

        $response->assertStatus(200)
            ->assertJsonPath('data.data.0.permissions', function ($permissions) {
                return count($permissions) === 2;
            });
    }

    /**
     * Test response has correct JSON structure
     */
    public function test_response_has_correct_json_structure(): void
    {
        Module::factory()->create();

        $response = $this->actingAs($this->admin)->getJson('/api/modules');

        $response->assertJsonStructure([
            'error',
            'message',
            'data' => [
                'data',
                'current_page',
                'total',
                'per_page',
            ],
        ]);
    }

    /**
     * Test filtering by multiple criteria
     */
    public function test_admin_can_filter_by_multiple_criteria(): void
    {
        Module::factory()->create(['name' => 'user_management', 'slug' => 'user-management']);
        Module::factory()->create(['name' => 'user_analytics', 'slug' => 'user-analytics']);
        Module::factory()->create(['name' => 'product_management', 'slug' => 'product-management']);

        $response = $this->actingAs($this->admin)->getJson('/api/modules?name=user&slug=user-management');

        $response->assertStatus(200)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.slug', 'user-management');
    }

    /**
     * Test pagination with second page
     */
    public function test_admin_can_navigate_to_second_page(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            Module::factory()->create(['name' => "module_{$i}"]);
        }

        $response = $this->actingAs($this->admin)->getJson('/api/modules?per_page=15&page=2');

        $response->assertStatus(200)
            ->assertJsonPath('data.current_page', 2)
            ->assertJsonPath('data.total', 30);
    }

    /**
     * Test default error format when no modules exist
     */
    public function test_default_per_page_is_15(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            Module::factory()->create();
        }

        $response = $this->actingAs($this->admin)->getJson('/api/modules');

        $response->assertStatus(200)
            ->assertJsonPath('data.per_page', 15)
            ->assertJsonPath('data.total', 25)
            ->assertJsonPath('data.current_page', 1);
    }

    /**
     * Test validation with invalid per_page parameter
     */
    public function test_validation_fails_with_invalid_per_page(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/modules?per_page=999');

        $response->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    /**
     * Test validation with negative per_page
     */
    public function test_validation_fails_with_negative_per_page(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/modules?per_page=-5');

        $response->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }
}
