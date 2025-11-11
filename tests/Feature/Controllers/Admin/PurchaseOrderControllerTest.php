<?php

namespace Tests\Feature\Controllers\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PurchaseOrderControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_list_purchase_orders(): void
    {
        $this->actingAs($this->admin);

        PurchaseOrder::factory()->count(5)->create();

        $response = $this->getJson('/api/admin/purchase-orders');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'data' => [
                    'current_page',
                    'data' => [
                        '*' => [
                            'id',
                            'product_id',
                            'supplier_id',
                            'purchase_price',
                            'quantity',
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
                'message' => 'Purchase orders retrieved successfully.',
            ]);
    }

    public function test_admin_list_purchase_orders_with_pagination(): void
    {
        $this->actingAs($this->admin);

        PurchaseOrder::factory()->count(25)->create();

        $response = $this->getJson('/api/admin/purchase-orders?per_page=5');

        $response->assertStatus(200)
            ->assertJsonPath('data.per_page', 5)
            ->assertJsonPath('data.total', 25);

        $this->assertCount(5, $response->json('data.data'));
    }

    public function test_admin_create_purchase_order(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create();
        $supplier = Supplier::factory()->create();

        $data = [
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'purchase_price' => 99.99,
            'quantity' => 50,
        ];

        $response = $this->postJson('/api/admin/purchase-orders', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'error',
                'data' => [
                    'id',
                    'product_id',
                    'supplier_id',
                    'purchase_price',
                    'quantity',
                    'created_at',
                    'updated_at',
                ],
                'message',
            ])
            ->assertJson([
                'error' => false,
                'message' => 'Purchase order created successfully.',
                'data' => [
                    'product_id' => $product->id,
                    'supplier_id' => $supplier->id,
                    'purchase_price' => 99.99,
                    'quantity' => 50,
                ],
            ]);

        $this->assertDatabaseHas('purchase_orders', [
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'purchase_price' => 99.99,
            'quantity' => 50,
        ]);
    }

    public function test_purchase_order_creation_validates_required_fields(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/admin/purchase-orders', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'product_id',
                'supplier_id',
                'purchase_price',
                'quantity',
            ]);
    }

    public function test_purchase_order_creation_validates_product_exists(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create();

        $data = [
            'product_id' => 999999, // Non-existent product
            'supplier_id' => $supplier->id,
            'purchase_price' => 99.99,
            'quantity' => 50,
        ];

        $response = $this->postJson('/api/admin/purchase-orders', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);
    }

    public function test_purchase_order_creation_validates_supplier_exists(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create();

        $data = [
            'product_id' => $product->id,
            'supplier_id' => 999999, // Non-existent supplier
            'purchase_price' => 99.99,
            'quantity' => 50,
        ];

        $response = $this->postJson('/api/admin/purchase-orders', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['supplier_id']);
    }

    public function test_purchase_order_creation_validates_numeric_fields(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create();
        $supplier = Supplier::factory()->create();

        $data = [
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'purchase_price' => 'invalid',
            'quantity' => 'invalid',
        ];

        $response = $this->postJson('/api/admin/purchase-orders', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'purchase_price',
                'quantity',
            ]);
    }

    public function test_purchase_order_creation_validates_minimum_values(): void
    {
        $this->actingAs($this->admin);

        $product = Product::factory()->create();
        $supplier = Supplier::factory()->create();

        $data = [
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'purchase_price' => -1,
            'quantity' => 0,
        ];

        $response = $this->postJson('/api/admin/purchase-orders', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'purchase_price',
                'quantity',
            ]);
    }

    public function test_unauthenticated_user_cannot_access_purchase_orders(): void
    {
        $response = $this->getJson('/api/admin/purchase-orders');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_create_purchase_orders(): void
    {
        $product = Product::factory()->create();
        $supplier = Supplier::factory()->create();

        $data = [
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'purchase_price' => 99.99,
            'quantity' => 50,
        ];

        $response = $this->postJson('/api/admin/purchase-orders', $data);

        $response->assertStatus(401);
    }
}
