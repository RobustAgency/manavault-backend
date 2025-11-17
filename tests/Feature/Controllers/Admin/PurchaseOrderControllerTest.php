<?php

namespace Tests\Feature\Controllers\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use Illuminate\Support\Facades\Http;
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
                            'order_number',
                            'supplier_id',
                            'total_price',
                            'status',
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

    public function test_admin_show_purchase_order(): void
    {
        $this->actingAs($this->admin);

        $purchaseOrder = PurchaseOrder::factory()->create();

        $response = $this->getJson("/api/admin/purchase-orders/{$purchaseOrder->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'data' => [
                    'id',
                    'order_number',
                    'supplier_id',
                    'total_price',
                    'status',
                    'created_at',
                    'updated_at',
                    'supplier',
                    'items',
                    'vouchers',
                ],
                'message',
            ])
            ->assertJson([
                'error' => false,
                'message' => 'Purchase order retrieved successfully.',
                'data' => [
                    'id' => $purchaseOrder->id,
                    'order_number' => $purchaseOrder->order_number,
                ],
            ]);
    }

    public function test_admin_create_purchase_order_with_internal_supplier(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create([
            'type' => 'internal',
        ]);
        $digitalProduct = DigitalProduct::factory()->create([
            'cost_price' => 10.00,
        ]);

        $data = [
            'supplier_id' => $supplier->id,
            'items' => [
                [
                    'digital_product_id' => $digitalProduct->id,
                    'quantity' => 5,
                ],
            ],
        ];

        $response = $this->postJson('/api/admin/purchase-orders', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'error',
                'data' => [
                    'id',
                    'order_number',
                    'supplier_id',
                    'total_price',
                    'status',
                    'created_at',
                    'updated_at',
                ],
                'message',
            ])
            ->assertJson([
                'error' => false,
                'message' => 'Purchase order created successfully.',
                'data' => [
                    'supplier_id' => $supplier->id,
                    'total_price' => 50.00,
                    'status' => 'completed',
                ],
            ]);

        $this->assertDatabaseHas('purchase_orders', [
            'supplier_id' => $supplier->id,
            'total_price' => 50.00,
            'status' => 'completed',
        ]);
    }

    public function test_admin_create_purchase_order_with_gift2games_supplier(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create([
            'name' => 'Gift2Games',
            'slug' => 'gift2games',
            'type' => 'external',
        ]);

        $digitalProduct = DigitalProduct::factory()->create([
            'sku' => '12345',
            'cost_price' => 10.00,
        ]);

        Http::fake([
            '*/create_order' => Http::response([
                'status' => 'success',
                'data' => [
                    'referenceNumber' => 'PO-20251117-TEST',
                    'productId' => 12345,
                    'code' => 'VOUCHER-CODE-123',
                    'pin' => '1234',
                    'serial' => 'SERIAL-123',
                    'expiryDate' => '2025-12-31',
                ],
            ], 200),
        ]);

        $data = [
            'supplier_id' => $supplier->id,
            'items' => [
                [
                    'digital_product_id' => $digitalProduct->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->postJson('/api/admin/purchase-orders', $data);

        $response->assertStatus(201)
            ->assertJson([
                'error' => false,
                'message' => 'Purchase order created successfully.',
                'data' => [
                    'supplier_id' => $supplier->id,
                    'status' => 'completed',
                ],
            ]);

        $this->assertDatabaseHas('purchase_orders', [
            'supplier_id' => $supplier->id,
            'status' => 'completed',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/create_order') &&
                   $request->method() === 'POST';
        });
    }

    public function test_admin_create_purchase_order_with_ezcards_supplier(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create([
            'name' => 'EzCards',
            'slug' => 'ez_cards',
            'type' => 'external',
        ]);

        $digitalProduct = DigitalProduct::factory()->create([
            'sku' => 'AAU-QB-Q1J',
            'cost_price' => 22.88,
        ]);

        Http::fake([
            '*/v2/orders' => Http::response([
                'requestId' => 'test-request-id',
                'data' => [
                    'transactionId' => '1234',
                    'clientOrderNumber' => 'PO-20251117-TEST',
                    'status' => 'PROCESSING',
                    'grandTotal' => [
                        'amount' => '45.76',
                        'currency' => 'USD',
                    ],
                    'products' => [
                        [
                            'sku' => 'AAU-QB-Q1J',
                            'quantity' => 2,
                            'status' => 'PROCESSING',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $data = [
            'supplier_id' => $supplier->id,
            'items' => [
                [
                    'digital_product_id' => $digitalProduct->id,
                    'quantity' => 2,
                ],
            ],
        ];

        $response = $this->postJson('/api/admin/purchase-orders', $data);

        $response->assertStatus(201)
            ->assertJson([
                'error' => false,
                'message' => 'Purchase order created successfully.',
                'data' => [
                    'supplier_id' => $supplier->id,
                    'status' => 'processing',
                ],
            ]);

        $this->assertDatabaseHas('purchase_orders', [
            'supplier_id' => $supplier->id,
            'status' => 'processing',
            'transaction_id' => '1234',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/v2/orders') &&
                   $request->method() === 'POST';
        });
    }

    public function test_admin_create_purchase_order_with_multiple_items(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create([
            'type' => 'internal',
        ]);

        $digitalProduct1 = DigitalProduct::factory()->create(['cost_price' => 10.00]);
        $digitalProduct2 = DigitalProduct::factory()->create(['cost_price' => 15.00]);

        $data = [
            'supplier_id' => $supplier->id,
            'items' => [
                [
                    'digital_product_id' => $digitalProduct1->id,
                    'quantity' => 3,
                ],
                [
                    'digital_product_id' => $digitalProduct2->id,
                    'quantity' => 2,
                ],
            ],
        ];

        $response = $this->postJson('/api/admin/purchase-orders', $data);

        $response->assertStatus(201)
            ->assertJson([
                'error' => false,
                'message' => 'Purchase order created successfully.',
                'data' => [
                    'supplier_id' => $supplier->id,
                    'total_price' => 60.00,
                ],
            ]);

        $this->assertDatabaseHas('purchase_orders', [
            'supplier_id' => $supplier->id,
            'total_price' => 60.00,
        ]);
    }

    public function test_purchase_order_creation_validates_required_fields(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/admin/purchase-orders', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'supplier_id',
                'items',
            ]);
    }

    public function test_purchase_order_creation_validates_items_structure(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create();

        $data = [
            'supplier_id' => $supplier->id,
            'items' => [
                [
                    'digital_product_id' => 999999,
                    'quantity' => 'invalid',
                ],
            ],
        ];

        $response = $this->postJson('/api/admin/purchase-orders', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'items.0.digital_product_id',
                'items.0.quantity',
            ]);
    }

    public function test_purchase_order_creation_validates_supplier_exists(): void
    {
        $this->actingAs($this->admin);

        $digitalProduct = DigitalProduct::factory()->create();

        $data = [
            'supplier_id' => 999999,
            'items' => [
                [
                    'digital_product_id' => $digitalProduct->id,
                    'quantity' => 5,
                ],
            ],
        ];

        $response = $this->postJson('/api/admin/purchase-orders', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['supplier_id']);
    }

    public function test_purchase_order_creation_validates_digital_product_exists(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create();

        $data = [
            'supplier_id' => $supplier->id,
            'items' => [
                [
                    'digital_product_id' => 999999,
                    'quantity' => 5,
                ],
            ],
        ];

        $response = $this->postJson('/api/admin/purchase-orders', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.digital_product_id']);
    }

    public function test_purchase_order_creation_validates_minimum_quantity(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create();
        $digitalProduct = DigitalProduct::factory()->create();

        $data = [
            'supplier_id' => $supplier->id,
            'items' => [
                [
                    'digital_product_id' => $digitalProduct->id,
                    'quantity' => 0,
                ],
            ],
        ];

        $response = $this->postJson('/api/admin/purchase-orders', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);
    }

    public function test_purchase_order_creation_requires_at_least_one_item(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create();

        $data = [
            'supplier_id' => $supplier->id,
            'items' => [],
        ];

        $response = $this->postJson('/api/admin/purchase-orders', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_purchase_order_creation_handles_external_api_failure(): void
    {
        $this->actingAs($this->admin);

        $supplier = Supplier::factory()->create([
            'slug' => 'ez_cards',
            'type' => 'external',
        ]);

        $digitalProduct = DigitalProduct::factory()->create([
            'sku' => 'FAIL-SKU',
        ]);

        Http::fake([
            '*/v2/orders' => Http::response(['error' => 'API Error'], 500),
        ]);

        $data = [
            'supplier_id' => $supplier->id,
            'items' => [
                [
                    'digital_product_id' => $digitalProduct->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->postJson('/api/admin/purchase-orders', $data);

        $response->assertStatus(201)
            ->assertJson([
                'error' => false,
                'data' => [
                    'status' => 'failed',
                ],
            ]);
    }

    public function test_unauthenticated_user_cannot_access_purchase_orders(): void
    {
        $response = $this->getJson('/api/admin/purchase-orders');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_create_purchase_orders(): void
    {
        $supplier = Supplier::factory()->create();
        $digitalProduct = DigitalProduct::factory()->create();

        $data = [
            'supplier_id' => $supplier->id,
            'items' => [
                [
                    'digital_product_id' => $digitalProduct->id,
                    'quantity' => 5,
                ],
            ],
        ];

        $response = $this->postJson('/api/admin/purchase-orders', $data);

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_view_purchase_order(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create();

        $response = $this->getJson("/api/admin/purchase-orders/{$purchaseOrder->id}");

        $response->assertStatus(401);
    }
}
