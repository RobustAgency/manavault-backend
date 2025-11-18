<?php

namespace Tests\Feature\Controllers\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Supplier;
use App\Models\DigitalProduct;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DigitalProductControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_list_digital_products(): void
    {
        $this->actingAs($this->admin);

        DigitalProduct::factory()->count(5)->create();

        $response = $this->getJson('/api/admin/digital-products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'data' => [
                    'current_page',
                    'data' => [
                        '*' => [
                            'id',
                            'supplier_id',
                            'name',
                            'sku',
                            'brand',
                            'description',
                            'cost_price',
                            'status',
                            'metadata',
                            'last_synced_at',
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
                'message' => 'Digital products retrieved successfully.',
            ]);
    }

    public function test_admin_list_digital_products_with_filters(): void
    {
        $this->actingAs($this->admin);

        $supplier1 = Supplier::factory()->create();
        $supplier2 = Supplier::factory()->create();

        DigitalProduct::factory()->count(3)->create([
            'supplier_id' => $supplier1->id,
            'status' => 'active',
        ]);
        DigitalProduct::factory()->count(2)->create([
            'supplier_id' => $supplier2->id,
            'status' => 'inactive',
        ]);

        $response = $this->getJson("/api/admin/digital-products?supplier_id={$supplier1->id}&status=active");

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data.data'));
    }

    public function test_admin_list_digital_products_with_pagination(): void
    {
        $this->actingAs($this->admin);

        DigitalProduct::factory()->count(25)->create();

        $response = $this->getJson('/api/admin/digital-products?per_page=5');

        $response->assertStatus(200)
            ->assertJsonPath('data.per_page', 5)
            ->assertJsonPath('data.total', 25);

        $this->assertCount(5, $response->json('data.data'));
    }

    public function test_admin_show_digital_product(): void
    {
        $this->actingAs($this->admin);

        $digitalProduct = DigitalProduct::factory()->create([
            'name' => 'Test Product',
            'cost_price' => 99.99,
        ]);

        $response = $this->getJson("/api/admin/digital-products/{$digitalProduct->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'data' => [
                    'id',
                    'supplier_id',
                    'name',
                    'sku',
                    'brand',
                    'description',
                    'cost_price',
                    'status',
                    'metadata',
                    'last_synced_at',
                    'created_at',
                    'updated_at',
                    'supplier',
                ],
                'message',
            ])
            ->assertJson([
                'error' => false,
                'message' => 'Digital product retrieved successfully.',
                'data' => [
                    'id' => $digitalProduct->id,
                    'name' => 'Test Product',
                    'cost_price' => '99.99',
                ],
            ]);
    }

    public function test_admin_show_nonexistent_digital_product(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/admin/digital-products/999999');

        $response->assertStatus(404);
    }

    public function test_admin_create_single_digital_product(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create();

        $data = [
            'products' => [
                [
                    'supplier_id' => $supplier->id,
                    'name' => 'New Digital Product',
                    'sku' => 'SKU-12345',
                    'brand' => 'Test Brand',
                    'description' => 'Product description',
                    'cost_price' => 149.99,
                    'status' => 'active',
                    'metadata' => ['external_id' => 'ext-123'],
                ],
            ],
        ];

        $response = $this->postJson('/api/admin/digital-products', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'error',
                'data' => [
                    '*' => [
                        'id',
                        'supplier_id',
                        'name',
                        'brand',
                        'cost_price',
                        'status',
                    ],
                ],
                'message',
            ])
            ->assertJson([
                'error' => false,
                'message' => 'Digital products created successfully.',
            ]);

        $this->assertDatabaseHas('digital_products', [
            'name' => 'New Digital Product',
            'sku' => 'SKU-12345',
            'brand' => 'Test Brand',
            'cost_price' => 149.99,
        ]);
    }

    public function test_admin_create_multiple_digital_products(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create();

        $data = [
            'products' => [
                [
                    'supplier_id' => $supplier->id,
                    'name' => 'Product 1',
                    'sku' => 'SKU-001',
                    'brand' => 'Brand A',
                    'cost_price' => 10.00,
                    'status' => 'active',
                ],
                [
                    'supplier_id' => $supplier->id,
                    'name' => 'Product 2',
                    'sku' => 'SKU-002',
                    'brand' => 'Brand B',
                    'cost_price' => 20.00,
                    'status' => 'active',
                ],
                [
                    'supplier_id' => $supplier->id,
                    'name' => 'Product 3',
                    'sku' => 'SKU-003',
                    'brand' => 'Brand C',
                    'cost_price' => 30.00,
                    'status' => 'inactive',
                ],
            ],
        ];

        $response = $this->postJson('/api/admin/digital-products', $data);

        $response->assertStatus(201);
        $this->assertCount(3, $response->json('data'));
        $this->assertDatabaseCount('digital_products', 3);
        $this->assertDatabaseHas('digital_products', ['name' => 'Product 1']);
        $this->assertDatabaseHas('digital_products', ['name' => 'Product 2']);
        $this->assertDatabaseHas('digital_products', ['name' => 'Product 3']);
    }

    public function test_admin_create_digital_product_validation_fails(): void
    {
        $this->actingAs($this->admin);

        $data = [
            'products' => [
                [
                    // Missing required fields
                    'name' => 'Test Product',
                ],
            ],
        ];

        $response = $this->postJson('/api/admin/digital-products', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'products.0.supplier_id',
                'products.0.sku',
                'products.0.cost_price',
                'products.0.status',
            ]);
    }

    public function test_admin_update_digital_product(): void
    {
        $this->actingAs($this->admin);

        $digitalProduct = DigitalProduct::factory()->create([
            'name' => 'Original Name',
            'cost_price' => 100.00,
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'brand' => 'Updated Brand',
            'cost_price' => 199.99,
            'status' => 'inactive',
        ];

        $response = $this->postJson("/api/admin/digital-products/{$digitalProduct->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'data' => [
                    'id',
                    'name',
                    'brand',
                    'cost_price',
                    'status',
                ],
                'message',
            ])
            ->assertJson([
                'error' => false,
                'message' => 'Digital product updated successfully.',
                'data' => [
                    'id' => $digitalProduct->id,
                    'name' => 'Updated Name',
                    'cost_price' => '199.99',
                    'status' => 'inactive',
                ],
            ]);

        $this->assertDatabaseHas('digital_products', [
            'id' => $digitalProduct->id,
            'name' => 'Updated Name',
            'cost_price' => 199.99,
        ]);
    }

    public function test_admin_delete_digital_product(): void
    {
        $this->actingAs($this->admin);

        $digitalProduct = DigitalProduct::factory()->create();

        $response = $this->deleteJson("/api/admin/digital-products/{$digitalProduct->id}");

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Digital product deleted successfully.',
            ]);

        $this->assertDatabaseMissing('digital_products', [
            'id' => $digitalProduct->id,
        ]);
    }

    public function test_admin_delete_nonexistent_digital_product(): void
    {
        $this->actingAs($this->admin);

        $response = $this->deleteJson('/api/admin/digital-products/999999');

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_access_digital_products(): void
    {
        $response = $this->getJson('/api/admin/digital-products');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_create_digital_product(): void
    {
        $supplier = Supplier::factory()->create();

        $data = [
            'products' => [
                [
                    'supplier_id' => $supplier->id,
                    'name' => 'Test Product',
                    'sku' => 'TEST-SKU',
                    'cost_price' => 99.99,
                    'status' => 'active',
                ],
            ],
        ];

        $response = $this->postJson('/api/admin/digital-products', $data);

        $response->assertStatus(401);
    }

    public function test_list_digital_products_validates_pagination_limits(): void
    {
        $this->actingAs($this->admin);

        // Test per_page exceeds maximum
        $response = $this->getJson('/api/admin/digital-products?per_page=150');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }
}
