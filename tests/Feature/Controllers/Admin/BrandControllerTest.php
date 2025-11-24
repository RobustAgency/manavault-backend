<?php

namespace Tests\Feature\Controllers\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Brand;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BrandControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $admin;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->user = User::factory()->create(['role' => 'user']);
    }

    public function test_admin_can_list_brands(): void
    {
        $this->actingAs($this->admin);

        Brand::factory()->count(5)->create();

        $response = $this->getJson('/api/admin/brands');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'data' => [
                    'current_page',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'per_page',
                    'total',
                    'last_page',
                ],
                'message',
            ])
            ->assertJson([
                'error' => false,
                'message' => 'Brands retrieved successfully.',
            ]);
    }

    public function test_admin_can_list_brands_with_pagination(): void
    {
        $this->actingAs($this->admin);

        Brand::factory()->count(25)->create();

        $response = $this->getJson('/api/admin/brands?per_page=10');

        $response->assertStatus(200)
            ->assertJsonPath('data.per_page', 10)
            ->assertJsonPath('data.total', 25);

        $this->assertCount(10, $response->json('data.data'));
    }

    public function test_admin_can_filter_brands_by_name(): void
    {
        $this->actingAs($this->admin);

        Brand::factory()->create(['name' => 'Nike']);
        Brand::factory()->create(['name' => 'Adidas']);
        Brand::factory()->create(['name' => 'Puma']);

        $response = $this->getJson('/api/admin/brands?name=Nike');

        $response->assertStatus(200)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.name', 'Nike');
    }

    public function test_admin_can_create_brand(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/admin/brands', [
            'name' => 'New Brand',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'error' => false,
                'message' => 'Brand created successfully.',
                'data' => [
                    'name' => 'New Brand',
                ],
            ]);

        $this->assertDatabaseHas('brands', ['name' => 'New Brand']);
    }

    public function test_create_brand_validates_name_is_required(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/admin/brands', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_brand_validates_name_is_unique(): void
    {
        $this->actingAs($this->admin);

        Brand::factory()->create(['name' => 'Existing Brand']);

        $response = $this->postJson('/api/admin/brands', [
            'name' => 'Existing Brand',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_brand_validates_name_max_length(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/admin/brands', [
            'name' => str_repeat('a', 256),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_show_brand_returns_404_for_nonexistent_brand(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/admin/brands/99999');

        $response->assertStatus(404);
    }

    public function test_update_brand_returns_404_for_nonexistent_brand(): void
    {
        $this->actingAs($this->admin);

        $response = $this->putJson('/api/admin/brands/99999', [
            'name' => 'New Name',
        ]);

        $response->assertStatus(404);
    }

    public function test_delete_brand_returns_404_for_nonexistent_brand(): void
    {
        $this->actingAs($this->admin);

        $response = $this->deleteJson('/api/admin/brands/99999');

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_list_brands(): void
    {
        $response = $this->getJson('/api/admin/brands');

        $response->assertStatus(401);
    }

    public function test_non_admin_user_cannot_list_brands(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/admin/brands');

        $response->assertStatus(403);
    }

    public function test_non_admin_user_cannot_create_brand(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/admin/brands', [
            'name' => 'New Brand',
        ]);

        $response->assertStatus(403);
    }

    public function test_list_brands_validates_per_page_parameter(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/admin/brands?per_page=invalid');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_list_brands_validates_per_page_minimum(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/admin/brands?per_page=0');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_list_brands_validates_per_page_maximum(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/admin/brands?per_page=101');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }
}
