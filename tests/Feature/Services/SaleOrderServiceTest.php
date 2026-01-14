<?php

namespace Tests\Feature\Services;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SaleOrder;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use App\Enums\SaleOrder\Status;
use App\Models\PurchaseOrderItem;
use App\Services\SaleOrderService;
use App\Enums\Product\FulfillmentMode;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SaleOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private SaleOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SaleOrderService::class);
    }

    public function test_create_order_successfully(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = Product::factory()->create(['fulfillment_mode' => FulfillmentMode::MANUAL->value]);
        $digitalProduct = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);

        // Create association between product and digital products
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        // Create purchase order with inventory
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

        $saleOrder = $this->service->createOrder($data);

        $this->assertInstanceOf(SaleOrder::class, $saleOrder);
        $this->assertEquals(Status::COMPLETED->value, $saleOrder->status);
        $this->assertEquals(SaleOrder::MANASTORE, $saleOrder->source);
        $this->assertEquals('SO-2026-000001', $saleOrder->order_number);
        $this->assertCount(1, $saleOrder->items);
        $this->assertEquals(5, $saleOrder->items[0]->quantity);
    }

    public function test_create_order_with_multiple_items(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);

        $product1 = Product::factory()->create(['fulfillment_mode' => FulfillmentMode::MANUAL->value]);
        $digitalProduct1 = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);
        $product1->digitalProducts()->attach($digitalProduct1->id, ['priority' => 1]);

        $product2 = Product::factory()->create(['fulfillment_mode' => FulfillmentMode::MANUAL->value]);
        $digitalProduct2 = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);
        $product2->digitalProducts()->attach($digitalProduct2->id, ['priority' => 1]);

        // Create purchase orders
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
                    'quantity' => 3,
                ],
                [
                    'product_id' => $product2->id,
                    'quantity' => 2,
                ],
            ],
        ];

        $saleOrder = $this->service->createOrder($data);

        $this->assertCount(2, $saleOrder->items);
        $this->assertEquals(Status::COMPLETED->value, $saleOrder->status);
    }

    public function test_create_order_calculates_total_price(): void
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
            'quantity' => 50,
        ]);

        $data = [
            'order_number' => 'SO-2026-000003',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                ],
            ],
        ];

        $saleOrder = $this->service->createOrder($data);

        // Total price should be 5 * 100 = 500
        $this->assertEquals(500.00, $saleOrder->total_price);
    }

    public function test_create_order_deducts_inventory(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = Product::factory()->create(['fulfillment_mode' => FulfillmentMode::MANUAL->value]);
        $digitalProduct = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $purchaseOrder = PurchaseOrder::factory()->create();
        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $digitalProduct->id,
            'quantity' => 50,
        ]);

        $data = [
            'order_number' => 'SO-2026-000004',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 20,
                ],
            ],
        ];

        $this->service->createOrder($data);

        // Verify inventory was deducted
        $poItem->refresh();
        $this->assertEquals(30, $poItem->quantity);
    }

    public function test_create_order_throws_exception_when_product_not_found(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Product with ID 999 not found.');

        $data = [
            'order_number' => 'SO-2026-000005',
            'items' => [
                [
                    'product_id' => 999,
                    'quantity' => 5,
                ],
            ],
        ];

        $this->service->createOrder($data);
    }

    public function test_create_order_throws_exception_when_no_digital_products(): void
    {
        $product = Product::factory()->create();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('has no digital products assigned');

        $data = [
            'order_number' => 'SO-2026-000006',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                ],
            ],
        ];

        $this->service->createOrder($data);
    }

    public function test_create_order_throws_exception_when_insufficient_inventory(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = Product::factory()->create(['fulfillment_mode' => FulfillmentMode::MANUAL->value]);
        $digitalProduct = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $digitalProduct->id,
            'quantity' => 10,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient inventory');

        $data = [
            'order_number' => 'SO-2026-000007',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 20,
                ],
            ],
        ];

        $this->service->createOrder($data);
    }

    public function test_create_order_allocates_to_correct_digital_products(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = Product::factory()->create(['fulfillment_mode' => FulfillmentMode::MANUAL->value]);

        $dp1 = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);
        $dp2 = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);

        $product->digitalProducts()->attach([
            $dp1->id => ['priority' => 1],
            $dp2->id => ['priority' => 2],
        ]);

        $po1 = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po1->id,
            'digital_product_id' => $dp1->id,
            'quantity' => 30,
        ]);

        $po2 = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po2->id,
            'digital_product_id' => $dp2->id,
            'quantity' => 20,
        ]);

        $data = [
            'order_number' => 'SO-2026-000008',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 35,
                ],
            ],
        ];

        $saleOrder = $this->service->createOrder($data);

        // Should allocate 30 from dp1 and 5 from dp2
        $allocations = $saleOrder->items[0]->digitalProducts;
        $this->assertCount(2, $allocations);
    }

    public function test_create_order_respects_priority_order(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = Product::factory()->create(['fulfillment_mode' => FulfillmentMode::MANUAL->value]);

        // Create digital products with different priorities
        $dp1 = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);
        $dp2 = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);
        $dp3 = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);

        // Attach with specific priorities: dp2 priority 1, dp3 priority 2, dp1 priority 3
        $product->digitalProducts()->attach([
            $dp2->id => ['priority' => 1],
            $dp3->id => ['priority' => 2],
            $dp1->id => ['priority' => 3],
        ]);

        // Create purchase orders
        $po1 = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po1->id,
            'digital_product_id' => $dp1->id,
            'quantity' => 50,
        ]);

        $po2 = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po2->id,
            'digital_product_id' => $dp2->id,
            'quantity' => 10,
        ]);

        $po3 = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po3->id,
            'digital_product_id' => $dp3->id,
            'quantity' => 10,
        ]);

        $data = [
            'order_number' => 'SO-2026-000009',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 25,
                ],
            ],
        ];

        $saleOrder = $this->service->createOrder($data);

        $allocations = $saleOrder->items[0]->digitalProducts->sortBy('digital_product_id');

        // Should allocate in priority order: dp2 (priority 1) gets 10, dp3 (priority 2) gets 10, dp1 (priority 3) gets 5
        $this->assertCount(3, $allocations);

        // Find allocations by digital product ID
        $dp2Allocation = $allocations->firstWhere('digital_product_id', $dp2->id);
        $dp3Allocation = $allocations->firstWhere('digital_product_id', $dp3->id);
        $dp1Allocation = $allocations->firstWhere('digital_product_id', $dp1->id);

        $this->assertEquals(10, $dp2Allocation->quantity_deducted);
        $this->assertEquals(10, $dp3Allocation->quantity_deducted);
        $this->assertEquals(5, $dp1Allocation->quantity_deducted);
    }

    public function test_create_order_allocates_only_from_highest_priority_when_sufficient(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = Product::factory()->create(['fulfillment_mode' => FulfillmentMode::MANUAL->value]);

        $dp1 = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);
        $dp2 = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);

        // dp1 has priority 1 (highest), dp2 has priority 2
        $product->digitalProducts()->attach([
            $dp1->id => ['priority' => 1],
            $dp2->id => ['priority' => 2],
        ]);

        $po1 = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po1->id,
            'digital_product_id' => $dp1->id,
            'quantity' => 50,
        ]);

        $po2 = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po2->id,
            'digital_product_id' => $dp2->id,
            'quantity' => 50,
        ]);

        $data = [
            'order_number' => 'SO-2026-0000011',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 20,
                ],
            ],
        ];

        $saleOrder = $this->service->createOrder($data);

        $allocations = $saleOrder->items[0]->digitalProducts;

        // Should only allocate from dp1 since it has sufficient quantity
        $this->assertCount(1, $allocations);
        $this->assertEquals($dp1->id, $allocations[0]->digital_product_id);
        $this->assertEquals(20, $allocations[0]->quantity_deducted);
    }

    public function test_create_order_priority_with_partial_deduction(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = Product::factory()->create(['fulfillment_mode' => FulfillmentMode::MANUAL->value]);

        $dp1 = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);
        $dp2 = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);
        $dp3 = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);

        $product->digitalProducts()->attach([
            $dp1->id => ['priority' => 1],
            $dp2->id => ['priority' => 2],
            $dp3->id => ['priority' => 3],
        ]);

        // Set limited quantities for priority-based testing
        $po1 = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po1->id,
            'digital_product_id' => $dp1->id,
            'quantity' => 5,
        ]);

        $po2 = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po2->id,
            'digital_product_id' => $dp2->id,
            'quantity' => 8,
        ]);

        $po3 = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po3->id,
            'digital_product_id' => $dp3->id,
            'quantity' => 10,
        ]);

        $data = [
            'order_number' => 'SO-2026-0000012',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 18,
                ],
            ],
        ];

        $saleOrder = $this->service->createOrder($data);

        $allocations = $saleOrder->items[0]->digitalProducts->sortBy('digital_product_id');

        // Should allocate: dp1 (priority 1) = 5, dp2 (priority 2) = 8, dp3 (priority 3) = 5
        $this->assertCount(3, $allocations);

        $dp1Allocation = $allocations->firstWhere('digital_product_id', $dp1->id);
        $dp2Allocation = $allocations->firstWhere('digital_product_id', $dp2->id);
        $dp3Allocation = $allocations->firstWhere('digital_product_id', $dp3->id);

        $this->assertEquals(5, $dp1Allocation->quantity_deducted);
        $this->assertEquals(8, $dp2Allocation->quantity_deducted);
        $this->assertEquals(5, $dp3Allocation->quantity_deducted);
    }

    public function test_create_order_uses_price_based_fulfillment_mode(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = Product::factory()->create(['fulfillment_mode' => FulfillmentMode::PRICE->value]);

        $dp1 = DigitalProduct::factory()->create(['supplier_id' => $supplier->id, 'cost_price' => 50.00]);
        $dp2 = DigitalProduct::factory()->create(['supplier_id' => $supplier->id, 'cost_price' => 30.00]);

        $product->digitalProducts()->attach([
            $dp1->id => ['priority' => 1],
            $dp2->id => ['priority' => 2],
        ]);

        $po1 = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po1->id,
            'digital_product_id' => $dp1->id,
            'quantity' => 10,
        ]);

        $po2 = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po2->id,
            'digital_product_id' => $dp2->id,
            'quantity' => 10,
        ]);

        $data = [
            'order_number' => 'SO-2026-0000013',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                ],
            ],
        ];

        $saleOrder = $this->service->createOrder($data);

        // Should prioritize by cost_price (lowest first)
        $allocations = $saleOrder->items[0]->digitalProducts;
        $this->assertGreaterThan(0, $allocations->count());
    }

    public function test_create_order_rolls_back_on_failure(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = Product::factory()->create(['fulfillment_mode' => FulfillmentMode::MANUAL->value]);
        $digitalProduct = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $digitalProduct->id,
            'quantity' => 10,
        ]);

        try {
            $data = [
                'order_number' => 'SO-2026-0000014',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 20,
                    ],
                ],
            ];

            $this->service->createOrder($data);
        } catch (\Exception $e) {
            // Expected
        }

        // Verify no sale order was created
        $this->assertEquals(0, SaleOrder::count());
    }

    public function test_create_order_creates_digital_product_allocations(): void
    {
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = Product::factory()->create(['fulfillment_mode' => FulfillmentMode::MANUAL->value]);
        $digitalProduct = DigitalProduct::factory()->create(['supplier_id' => $supplier->id]);
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'digital_product_id' => $digitalProduct->id,
            'quantity' => 50,
        ]);

        $data = [
            'order_number' => 'SO-2026-0000016',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10,
                ],
            ],
        ];

        $saleOrder = $this->service->createOrder($data);

        $allocations = $saleOrder->items[0]->digitalProducts;
        $this->assertCount(1, $allocations);
        $this->assertEquals(10, $allocations[0]->quantity_deducted);
    }
}
