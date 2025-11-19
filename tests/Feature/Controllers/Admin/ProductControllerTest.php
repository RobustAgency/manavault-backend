<?php

namespace Tests\Feature\Controllers\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_list_products(): void
    {
        $this->actingAs($this->admin);

        Product::factory()->count(5)->create();

        $response = $this->getJson('/api/admin/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'data' => [
                    'current_page',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'sku',
                            'brand',
                            'description',
                            'short_description',
                            'long_description',
                            'tags',
                            'image',
                            'selling_price',
                            'status',
                            'regions',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'per_page',
                    'total',
                ],
                'message',
            ])
            ->assertJson([
                'error' => false,
                'message' => 'Products retrieved successfully.',
            ]);
    }

    public function test_admin_list_products_with_status_filter(): void
    {
        $this->actingAs($this->admin);

        Product::factory()->count(3)->create(['status' => 'active']);
        Product::factory()->count(2)->create(['status' => 'inactive']);

        $response = $this->getJson('/api/admin/products?status=active');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data.data'));
    }

    public function test_admin_list_products_with_pagination(): void
    {
        $this->actingAs($this->admin);

        Product::factory()->count(25)->create();

        $response = $this->getJson('/api/admin/products?per_page=5');

        $response->assertStatus(200)
            ->assertJsonPath('data.per_page', 5)
            ->assertJsonPath('data.total', 25);

        $this->assertCount(5, $response->json('data.data'));
    }

    public function test_admin_show_product(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create([
            'name' => 'Test Product',
            'selling_price' => 99.99,
        ]);

        $response = $this->getJson("/api/admin/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'data' => [
                    'id',
                    'name',
                    'sku',
                    'brand',
                    'description',
                    'short_description',
                    'long_description',
                    'tags',
                    'image',
                    'selling_price',
                    'status',
                    'regions',
                    'created_at',
                    'updated_at',
                ],
                'message',
            ])
            ->assertJson([
                'error' => false,
                'message' => 'Product retrieved successfully.',
                'data' => [
                    'id' => $product->id,
                    'name' => 'Test Product',
                    'selling_price' => 99.99,
                ],
            ]);
    }

    public function test_admin_show_nonexistent_product(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/admin/products/999999');

        $response->assertStatus(404);
    }

    public function test_admin_create_product(): void
    {
        $this->actingAs($this->admin);

        Storage::fake('public');

        $data = [
            'name' => 'New Product',
            'description' => 'Product description',
            'sku' => 'SKU-12345',
            'brand' => 'Test Brand',
            'short_description' => 'Short description',
            'long_description' => 'Long description with more details',
            'tags' => ['gaming', 'digital'],
            'image' => UploadedFile::fake()->image('product.jpg'),
            'selling_price' => 149.99,
            'status' => 'active',
            'regions' => ['US', 'CA'],
        ];

        $response = $this->postJson('/api/admin/products', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'error',
                'data' => [
                    'id',
                    'name',
                    'sku',
                    'brand',
                    'description',
                    'short_description',
                    'long_description',
                    'tags',
                    'image',
                    'selling_price',
                    'status',
                    'regions',
                    'created_at',
                    'updated_at',
                ],
                'message',
            ])
            ->assertJson([
                'error' => false,
                'message' => 'Product created successfully.',
                'data' => [
                    'name' => 'New Product',
                    'description' => 'Product description',
                    'selling_price' => 149.99,
                    'status' => 'active',
                ],
            ]);

        $this->assertDatabaseHas('products', [
            'name' => 'New Product',
            'description' => 'Product description',
            'selling_price' => 149.99,
        ]);
    }

    public function test_admin_update_product(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create([
            'name' => 'Original Name',
            'selling_price' => 100.00,
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'brand' => 'Updated Brand',
            'short_description' => 'Updated short desc',
            'selling_price' => 199.99,
        ];

        $response = $this->postJson("/api/admin/products/{$product->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'data' => [
                    'id',
                    'name',
                    'sku',
                    'brand',
                    'description',
                    'short_description',
                    'long_description',
                    'tags',
                    'image',
                    'selling_price',
                    'status',
                    'regions',
                    'created_at',
                    'updated_at',
                ],
                'message',
            ])
            ->assertJson([
                'error' => false,
                'message' => 'Product updated successfully.',
                'data' => [
                    'id' => $product->id,
                    'name' => 'Updated Name',
                    'description' => 'Updated description',
                    'selling_price' => 199.99,
                ],
            ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'selling_price' => 199.99,
        ]);
    }

    public function test_admin_delete_product(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create();

        $response = $this->deleteJson("/api/admin/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Product deleted successfully.',
            ]);

        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
        ]);
    }

    public function test_admin_delete_nonexistent_product(): void
    {
        $this->actingAs($this->admin);

        $response = $this->deleteJson('/api/admin/products/999999');

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_access_products(): void
    {
        $response = $this->getJson('/api/admin/products');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_create_product(): void
    {
        $data = [
            'name' => 'Test Product',
            'selling_price' => 99.99,
        ];

        $response = $this->postJson('/api/admin/products', $data);

        $response->assertStatus(401);
    }

    public function test_list_products_validates_pagination_limits(): void
    {
        $this->actingAs($this->admin);

        // Test per_page exceeds maximum
        $response = $this->getJson('/api/admin/products?per_page=150');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }
}
