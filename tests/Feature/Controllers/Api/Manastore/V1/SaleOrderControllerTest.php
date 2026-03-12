<?php

namespace Tests\Feature\Controllers\Api\Manastore\V1;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Voucher;
use App\Models\Supplier;
use App\Models\SaleOrder;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use App\Enums\VoucherCodeStatus;
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
        $purchaseOrderItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $digitalProduct->id,
            'quantity' => 100,
        ]);

        // Create available vouchers for allocation
        Voucher::factory()->count(100)->create([
            'purchase_order_id' => $purchaseOrder->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'status' => VoucherCodeStatus::AVAILABLE->value,
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

        $product1 = Product::factory()->create();
        $digitalProduct1 = DigitalProduct::factory()->create(['selling_price' => 20.00, 'supplier_id' => $supplier->id]);
        $product1->digitalProducts()->attach($digitalProduct1->id);

        $product2 = Product::factory()->create();
        $digitalProduct2 = DigitalProduct::factory()->create(['supplier_id' => $supplier->id, 'selling_price' => 30.00]);
        $product2->digitalProducts()->attach($digitalProduct2->id);

        $po1 = PurchaseOrder::factory()->create();
        $poi1 = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po1->id,
            'digital_product_id' => $digitalProduct1->id,
            'quantity' => 50,
        ]);

        Voucher::factory()->count(50)->create([
            'purchase_order_id' => $po1->id,
            'purchase_order_item_id' => $poi1->id,
            'status' => VoucherCodeStatus::AVAILABLE->value,
        ]);

        $po2 = PurchaseOrder::factory()->create();
        $poi2 = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po2->id,
            'digital_product_id' => $digitalProduct2->id,
            'quantity' => 50,
        ]);

        Voucher::factory()->count(50)->create([
            'purchase_order_id' => $po2->id,
            'purchase_order_item_id' => $poi2->id,
            'status' => VoucherCodeStatus::AVAILABLE->value,
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
            'fulfillment_mode' => FulfillmentMode::MANUAL->value,
        ]);
        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'selling_price' => 100.00,

        ]);
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $purchaseOrder = PurchaseOrder::factory()->create();
        $purchaseOrderItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $digitalProduct->id,
            'quantity' => 100,
        ]);

        Voucher::factory()->count(100)->create([
            'purchase_order_id' => $purchaseOrder->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'status' => VoucherCodeStatus::AVAILABLE->value,
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
        $purchaseOrderItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $digitalProduct->id,
            'quantity' => 100,
        ]);

        Voucher::factory()->count(100)->create([
            'purchase_order_id' => $purchaseOrder->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'status' => VoucherCodeStatus::AVAILABLE->value,
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

    public function test_store_returns_order(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = Product::factory()->create(['fulfillment_mode' => FulfillmentMode::MANUAL->value]);
        $digitalProduct = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $purchaseOrder = PurchaseOrder::factory()->create();
        $purchaseOrderItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $digitalProduct->id,
            'quantity' => 100,
        ]);

        Voucher::factory()->count(100)->create([
            'purchase_order_id' => $purchaseOrder->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'status' => VoucherCodeStatus::AVAILABLE->value,
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
                ],
            ]);
    }

    public function test_get_vouchers_returns_allocated_vouchers(): void
    {
        // Arrange: Create product with digital product and vouchers
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = Product::factory()->create([
            'fulfillment_mode' => FulfillmentMode::MANUAL->value,
        ]);
        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'selling_price' => 100.00,
        ]);
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $purchaseOrder = PurchaseOrder::factory()->create();
        $purchaseOrderItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $digitalProduct->id,
            'quantity' => 5,
        ]);

        // Create completed vouchers
        $vouchers = Voucher::factory()->count(3)->create([
            'purchase_order_id' => $purchaseOrder->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'status' => VoucherCodeStatus::AVAILABLE->value,
        ]);

        // Create sale order
        $data = [
            'order_number' => 'SO-2026-GET-VOUCHERS-1',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sale-orders', $data, $this->getHeaders());
        $saleOrderId = $response->json('data.id');

        // Act: Get vouchers for the sale order
        $response = $this->getJson("/api/v1/sale-orders/{$saleOrderId}/vouchers", $this->getHeaders());

        // Assert: Response contains allocated vouchers
        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Voucher codes retrieved successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'title',
                        'codes' => [
                            '*' => [
                                'code',
                                'serial_number',
                                'pin_code',
                            ],
                        ],
                    ],
                ],
            ]);

        // Verify correct number of vouchers returned
        $this->assertCount(2, $response->json('data.0.codes'));
    }

    /**
     * Test: GET /sale-orders/{saleOrder}/vouchers with non-existent sale order.
     */
    public function test_get_vouchers_with_invalid_sale_order(): void
    {
        // Act: Try to get vouchers for non-existent sale order
        $response = $this->getJson('/api/v1/sale-orders/99999/vouchers', $this->getHeaders());

        // Assert: Response returns 404 for non-existent resource
        $response->assertStatus(404);
    }

    /**
     * Test: GET /sale-orders/{saleOrder}/vouchers returns empty array when no vouchers allocated.
     */
    public function test_get_vouchers_returns_empty_when_no_vouchers(): void
    {
        // Arrange: Create a sale order directly
        $saleOrder = SaleOrder::factory()->create();

        // Act: Get vouchers for the sale order
        $response = $this->getJson("/api/v1/sale-orders/{$saleOrder->id}/vouchers", $this->getHeaders());

        // Assert: Response contains empty data array
        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Voucher codes retrieved successfully.',
                'data' => [],
            ]);
    }

    /**
     * Test: GET /sale-orders/{saleOrder}/vouchers with multiple products.
     */
    public function test_get_vouchers_with_multiple_products(): void
    {
        // Arrange: Create two products with digital products and vouchers
        $supplier = Supplier::factory()->create(['type' => 'internal']);

        // Product 1 setup
        $product1 = Product::factory()->create([
            'fulfillment_mode' => FulfillmentMode::MANUAL->value,
        ]);
        $digitalProduct1 = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'selling_price' => 100.00,

        ]);
        $product1->digitalProducts()->attach($digitalProduct1->id, ['priority' => 1]);

        $purchaseOrder1 = PurchaseOrder::factory()->create();
        $purchaseOrderItem1 = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder1->id,
            'digital_product_id' => $digitalProduct1->id,
            'quantity' => 5,
        ]);

        // Create completed vouchers for product1
        $vouchers1 = Voucher::factory()->count(3)->create([
            'purchase_order_id' => $purchaseOrder1->id,
            'purchase_order_item_id' => $purchaseOrderItem1->id,
            'status' => VoucherCodeStatus::AVAILABLE->value,
        ]);

        // Product 2 setup
        $product2 = Product::factory()->create([
            'fulfillment_mode' => FulfillmentMode::MANUAL->value,
        ]);

        $digitalProduct2 = DigitalProduct::factory()->create(['supplier_id' => $supplier->id, 'selling_price' => 75.00]);
        $product2->digitalProducts()->attach($digitalProduct2->id, ['priority' => 1]);

        $purchaseOrder2 = PurchaseOrder::factory()->create();
        $purchaseOrderItem2 = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder2->id,
            'digital_product_id' => $digitalProduct2->id,
            'quantity' => 4,
        ]);

        // Create completed vouchers for product2
        $vouchers2 = Voucher::factory()->count(2)->create([
            'purchase_order_id' => $purchaseOrder2->id,
            'purchase_order_item_id' => $purchaseOrderItem2->id,
            'status' => VoucherCodeStatus::AVAILABLE->value,
        ]);

        // Create sale order with multiple products
        $data = [
            'order_number' => 'SO-2026-MULTI-PRODUCT',
            'items' => [
                [
                    'product_id' => $product1->id,
                    'quantity' => 2,
                ],
                [
                    'product_id' => $product2->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/sale-orders', $data, $this->getHeaders());
        $saleOrderId = $response->json('data.id');

        // Act: Get vouchers for the sale order
        $response = $this->getJson("/api/v1/sale-orders/{$saleOrderId}/vouchers", $this->getHeaders());

        // Assert: Response contains vouchers from all products
        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Voucher codes retrieved successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'title',
                        'codes' => [
                            '*' => [
                                'code',
                                'serial_number',
                                'pin_code',
                            ],
                        ],
                    ],
                ],
            ]);

        // Verify correct total number of vouchers returned (2 from product1 + 1 from product2)
        $this->assertCount(2, $response->json('data.0.codes'));
        $this->assertCount(1, $response->json('data.1.codes'));
    }
}
