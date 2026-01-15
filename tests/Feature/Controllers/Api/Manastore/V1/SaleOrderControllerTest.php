<?php

namespace Tests\Feature\Controllers\Api\Manastore\V1;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use App\Models\PurchaseOrderItem;
use App\Enums\Product\FulfillmentMode;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SaleOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $apiKey = 'test-api-key';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.manastore.api_key' => $this->apiKey]);
    }

    private function getHeaders(): array
    {
        return [
            'X-API-KEY' => $this->apiKey,
        ];
    }

    public function test_store_creates_sale_order_successfully(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = Product::factory()->create(['fulfillment_mode' => FulfillmentMode::MANUAL->value]);
        $digitalProduct = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);

        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $digitalProduct->id,
            'quantity' => 100,
        ]);

        $data = [
            'order_number' => 'SO-2026-000001',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sale-orders', $data, $this->getHeaders());

        $response->assertStatus(201)
            ->assertJson([
                'error' => false,
                'message' => 'Sale order created successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'order_number',
                    'source',
                    'total_price',
                    'status',
                ],
            ]);

        $this->assertDatabaseHas('sale_orders', [
            'order_number' => 'SO-2026-000001',
            'source' => 'manastore',
        ]);
    }

    public function test_store_with_multiple_items(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);

        $product1 = Product::factory()->create([
            'selling_price' => 50.00,
            'fulfillment_mode' => FulfillmentMode::MANUAL->value,
        ]);
        $digitalProduct1 = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);
        $product1->digitalProducts()->attach($digitalProduct1->id, ['priority' => 1]);

        $product2 = Product::factory()->create([
            'selling_price' => 30.00,
            'fulfillment_mode' => FulfillmentMode::MANUAL->value,
        ]);
        $digitalProduct2 = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);
        $product2->digitalProducts()->attach($digitalProduct2->id, ['priority' => 1]);

        $po1 = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po1->id,
            'digital_product_id' => $digitalProduct1->id,
            'quantity' => 50,
        ]);

        $po2 = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po2->id,
            'digital_product_id' => $digitalProduct2->id,
            'quantity' => 50,
        ]);

        $data = [
            'order_number' => 'SO-2026-000002',
            'items' => [
                [
                    'product_id' => $product1->id,
                    'quantity' => 2,
                ],
                [
                    'product_id' => $product2->id,
                    'quantity' => 3,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sale-orders', $data, $this->getHeaders());

        $response->assertStatus(201)
            ->assertJson(['error' => false]);

        $this->assertDatabaseHas('sale_orders', [
            'order_number' => 'SO-2026-000002',
        ]);
    }

    public function test_store_validates_required_order_number(): void
    {
        $product = Product::factory()->create();

        $data = [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sale-orders', $data, $this->getHeaders());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order_number']);
    }

    public function test_store_validates_items_min_one(): void
    {
        $data = [
            'order_number' => 'SO-2026-000001',
            'items' => [],
        ];

        $response = $this->postJson('/api/v1/sale-orders', $data, $this->getHeaders());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_store_validates_product_id_exists(): void
    {
        $data = [
            'order_number' => 'SO-2026-000001',
            'items' => [
                [
                    'product_id' => 999,
                    'quantity' => 5,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sale-orders', $data, $this->getHeaders());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.product_id']);
    }

    public function test_store_validates_quantity_required(): void
    {
        $product = Product::factory()->create();

        $data = [
            'order_number' => 'SO-2026-000001',
            'items' => [
                [
                    'product_id' => $product->id,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sale-orders', $data, $this->getHeaders());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);
    }

    public function test_store_validates_quantity_min_one(): void
    {
        $product = Product::factory()->create();

        $data = [
            'order_number' => 'SO-2026-000001',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 0,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sale-orders', $data, $this->getHeaders());

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.quantity']);
    }

    public function test_store_returns_error_when_product_not_found(): void
    {
        $data = [
            'order_number' => 'SO-2026-000001',
            'items' => [
                [
                    'product_id' => 999,
                    'quantity' => 5,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sale-orders', $data, $this->getHeaders());

        $response->assertStatus(422);
    }

    public function test_store_returns_error_when_no_digital_products(): void
    {
        $product = Product::factory()->create();

        $data = [
            'order_number' => 'SO-2026-000001',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sale-orders', $data, $this->getHeaders());

        $response->assertStatus(400)
            ->assertJson(['error' => true]);
    }

    public function test_store_returns_error_when_insufficient_inventory(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = Product::factory()->create(['fulfillment_mode' => FulfillmentMode::MANUAL->value]);
        $digitalProduct = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $digitalProduct->id,
            'quantity' => 5,
        ]);

        $data = [
            'order_number' => 'SO-2026-000001',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sale-orders', $data, $this->getHeaders());

        $response->assertStatus(400)
            ->assertJson(['error' => true]);
    }

    public function test_store_calculates_correct_total_price(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = Product::factory()->create([
            'selling_price' => 100.00,
            'fulfillment_mode' => FulfillmentMode::MANUAL->value,
        ]);
        $digitalProduct = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $digitalProduct->id,
            'quantity' => 100,
        ]);

        $data = [
            'order_number' => 'SO-2026-000001',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sale-orders', $data, $this->getHeaders());

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'total_price' => 500.00,
                ],
            ]);
    }

    public function test_store_with_source_parameter(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = Product::factory()->create(['fulfillment_mode' => FulfillmentMode::MANUAL->value]);
        $digitalProduct = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $digitalProduct->id,
            'quantity' => 100,
        ]);

        $data = [
            'order_number' => 'SO-2026-000001',
            'source' => 'api',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sale-orders', $data, $this->getHeaders());

        // Note: The service defaults to MANASTORE, but the request accepts source parameter
        $response->assertStatus(201)
            ->assertJson(['error' => false]);
    }

    public function test_store_returns_order_with_items(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = Product::factory()->create(['fulfillment_mode' => FulfillmentMode::MANUAL->value]);
        $digitalProduct = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $digitalProduct->id,
            'quantity' => 100,
        ]);

        $data = [
            'order_number' => 'SO-2026-000001',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sale-orders', $data, $this->getHeaders());

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'order_number',
                    'items' => [
                        '*' => [
                            'id',
                            'quantity',
                            'unit_price',
                            'subtotal',
                            'product_id',
                        ],
                    ],
                ],
            ]);
    }
}
