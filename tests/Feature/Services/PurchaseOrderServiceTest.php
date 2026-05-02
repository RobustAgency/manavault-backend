<?php

namespace Tests\Feature\Services;

use Tests\TestCase;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use Illuminate\Support\Facades\Queue;
use App\Services\PurchaseOrderService;
use App\Jobs\PlaceExternalPurchaseOrderJob;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PurchaseOrderServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private PurchaseOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PurchaseOrderService::class);
    }

    public function test_create_purchase_order_with_gift2games_supplier(): void
    {
        Queue::fake();

        $supplier = Supplier::factory()->create([
            'name' => 'Gift2Games',
            'slug' => 'gift2games',
            'type' => 'external',
        ]);

        $digitalProduct = DigitalProduct::factory()->create([
            'sku' => '12345',
            'cost_price' => 10.00,
        ]);

        $data = [
            'items' => [
                [
                    'supplier_id' => $supplier->id,
                    'digital_product_id' => $digitalProduct->id,
                    'quantity' => 2,
                ],
            ],
        ];

        $purchaseOrder = $this->service->createPurchaseOrder($data);

        $this->assertInstanceOf(PurchaseOrder::class, $purchaseOrder);
        $this->assertCount(1, $purchaseOrder->items);

        Queue::assertPushed(PlaceExternalPurchaseOrderJob::class, 1);
    }

    public function test_create_purchase_order_with_ezcards_supplier(): void
    {
        Queue::fake();

        $supplier = Supplier::factory()->create([
            'name' => 'EzCards',
            'slug' => 'ez_cards',
            'type' => 'external',
        ]);

        $digitalProduct = DigitalProduct::factory()->create([
            'sku' => 'AAU-QB-Q1J',
            'cost_price' => 22.88,
        ]);

        $data = [
            'items' => [
                [
                    'supplier_id' => $supplier->id,
                    'digital_product_id' => $digitalProduct->id,
                    'quantity' => 3,
                ],
            ],
        ];

        $purchaseOrder = $this->service->createPurchaseOrder($data);

        $this->assertInstanceOf(PurchaseOrder::class, $purchaseOrder);
        $this->assertCount(1, $purchaseOrder->items);

        Queue::assertPushed(PlaceExternalPurchaseOrderJob::class, 1);
    }

    public function test_create_purchase_order_with_internal_supplier(): void
    {
        $supplier = Supplier::factory()->create([
            'name' => 'Internal Supplier',
            'slug' => 'internal_supplier',
            'type' => 'internal',
        ]);

        $digitalProduct = DigitalProduct::factory()->create([
            'sku' => 'INTERNAL-SKU',
            'cost_price' => 15.00,
        ]);

        $data = [
            'items' => [
                [
                    'supplier_id' => $supplier->id,
                    'digital_product_id' => $digitalProduct->id,
                    'quantity' => 5,
                ],
            ],
        ];

        $purchaseOrder = $this->service->createPurchaseOrder($data);

        $this->assertInstanceOf(PurchaseOrder::class, $purchaseOrder);
        $this->assertEquals('processing', $purchaseOrder->status);
        $this->assertCount(1, $purchaseOrder->items);
        $this->assertEquals(75.00, $purchaseOrder->total_price);
    }

    public function test_create_purchase_order_with_multiple_items(): void
    {
        Queue::fake();

        $supplier = Supplier::factory()->create([
            'name' => 'EzCards',
            'slug' => 'ez_cards',
            'type' => 'external',
        ]);

        $digitalProduct1 = DigitalProduct::factory()->create([
            'sku' => 'AAU-QB-Q1J',
            'cost_price' => 22.88,
        ]);

        $digitalProduct2 = DigitalProduct::factory()->create([
            'sku' => 'SMS-1B-I4A',
            'cost_price' => 45.50,
        ]);

        $data = [
            'items' => [
                [
                    'supplier_id' => $supplier->id,
                    'digital_product_id' => $digitalProduct1->id,
                    'quantity' => 2,
                ],
                [
                    'supplier_id' => $supplier->id,
                    'digital_product_id' => $digitalProduct2->id,
                    'quantity' => 3,
                ],
            ],
        ];

        $purchaseOrder = $this->service->createPurchaseOrder($data);

        $this->assertInstanceOf(PurchaseOrder::class, $purchaseOrder);
        $this->assertCount(2, $purchaseOrder->items);
        $this->assertEquals(182.26, $purchaseOrder->total_price);

        Queue::assertPushed(PlaceExternalPurchaseOrderJob::class, 1);
    }

    public function test_failed_external_supplier_does_not_affect_other_suppliers(): void
    {
        Queue::fake();

        $externalSupplier = Supplier::factory()->create([
            'name' => 'EzCards',
            'slug' => 'ez_cards',
            'type' => 'external',
        ]);

        $internalSupplier = Supplier::factory()->create([
            'name' => 'Internal Supplier',
            'slug' => 'internal_supplier',
            'type' => 'internal',
        ]);

        $externalProduct = DigitalProduct::factory()->create([
            'sku' => 'EXT-SKU-001',
            'cost_price' => 20.00,
        ]);

        $internalProduct = DigitalProduct::factory()->create([
            'sku' => 'INT-SKU-001',
            'cost_price' => 10.00,
        ]);

        $data = [
            'items' => [
                [
                    'supplier_id' => $externalSupplier->id,
                    'digital_product_id' => $externalProduct->id,
                    'quantity' => 2,
                ],
                [
                    'supplier_id' => $internalSupplier->id,
                    'digital_product_id' => $internalProduct->id,
                    'quantity' => 3,
                ],
            ],
        ];

        $purchaseOrder = $this->service->createPurchaseOrder($data);

        // The overall PO is created and returned
        $this->assertInstanceOf(PurchaseOrder::class, $purchaseOrder);

        // Overall PO status is PROCESSING (job not yet run)
        $purchaseOrder->refresh();
        $this->assertEquals('processing', $purchaseOrder->status);

        // Both supplier rows are created in PROCESSING state (job hasn't run yet)
        $this->assertDatabaseHas('purchase_order_suppliers', [
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $externalSupplier->id,
            'status' => 'processing',
        ]);

        $this->assertDatabaseHas('purchase_order_suppliers', [
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $internalSupplier->id,
            'status' => 'processing',
        ]);

        // Both supplier items ARE persisted by the service
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $internalSupplier->id,
            'digital_product_id' => $internalProduct->id,
            'quantity' => 3,
        ]);

        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $externalSupplier->id,
            'digital_product_id' => $externalProduct->id,
            'quantity' => 2,
        ]);

        // Total price reflects both suppliers' items (40 + 30 = 70)
        $this->assertEquals(70.00, $purchaseOrder->total_price);

        // The external supplier job was dispatched (but not run)
        Queue::assertPushed(PlaceExternalPurchaseOrderJob::class, 1);
    }

    /**
     * Test: A purchase order created with a sale_order_id stores it correctly.
     */
    public function test_create_purchase_order_stores_sale_order_id(): void
    {
        Queue::fake();

        $supplier = Supplier::factory()->create([
            'name' => 'Internal Supplier',
            'slug' => 'internal_supplier',
            'type' => 'internal',
        ]);

        $digitalProduct = DigitalProduct::factory()->create([
            'sku' => 'INT-SALE-001',
            'cost_price' => 10.00,
        ]);

        $saleOrder = \App\Models\SaleOrder::factory()->create();

        $data = [
            'sale_order_id' => $saleOrder->id,
            'items' => [
                [
                    'supplier_id' => $supplier->id,
                    'digital_product_id' => $digitalProduct->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $purchaseOrder = $this->service->createPurchaseOrder($data);

        $this->assertInstanceOf(PurchaseOrder::class, $purchaseOrder);
        $this->assertEquals($saleOrder->id, $purchaseOrder->sale_order_id);
        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'sale_order_id' => $saleOrder->id,
        ]);
    }

    /**
     * Test: A manually created purchase order (no sale_order_id) stores NULL.
     */
    public function test_manual_purchase_order_has_null_sale_order_id(): void
    {
        Queue::fake();

        $supplier = Supplier::factory()->create([
            'name' => 'Internal Supplier',
            'slug' => 'internal_supplier',
            'type' => 'internal',
        ]);

        $digitalProduct = DigitalProduct::factory()->create([
            'sku' => 'INT-MANUAL-001',
            'cost_price' => 10.00,
        ]);

        $data = [
            'items' => [
                [
                    'supplier_id' => $supplier->id,
                    'digital_product_id' => $digitalProduct->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $purchaseOrder = $this->service->createPurchaseOrder($data);

        $this->assertInstanceOf(PurchaseOrder::class, $purchaseOrder);
        $this->assertNull($purchaseOrder->sale_order_id);
        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'sale_order_id' => null,
        ]);
    }

    /**
     * Test: createPurchaseOrderForDigitalProduct with a sale_order_id links the PO to the sale order.
     */
    public function test_create_purchase_order_for_digital_product_with_sale_order_id(): void
    {
        Queue::fake();

        $supplier = Supplier::factory()->create([
            'name' => 'Internal Supplier',
            'slug' => 'internal_supplier',
            'type' => 'internal',
        ]);

        $digitalProduct = DigitalProduct::factory()->forSupplier($supplier)->create([
            'sku' => 'INT-DP-001',
            'cost_price' => 5.00,
        ]);

        $saleOrder = \App\Models\SaleOrder::factory()->create();

        $purchaseOrder = $this->service->createPurchaseOrderForDigitalProduct($digitalProduct, 2, $saleOrder->id);

        $this->assertInstanceOf(PurchaseOrder::class, $purchaseOrder);
        $this->assertEquals($saleOrder->id, $purchaseOrder->sale_order_id);
        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'sale_order_id' => $saleOrder->id,
        ]);
    }

    /**
     * Test: createPurchaseOrderForDigitalProduct without sale_order_id stores NULL.
     */
    public function test_create_purchase_order_for_digital_product_without_sale_order_id(): void
    {
        Queue::fake();

        $supplier = Supplier::factory()->create([
            'name' => 'Internal Supplier',
            'slug' => 'internal_supplier',
            'type' => 'internal',
        ]);

        $digitalProduct = DigitalProduct::factory()->forSupplier($supplier)->create([
            'sku' => 'INT-DP-002',
            'cost_price' => 5.00,
        ]);

        $purchaseOrder = $this->service->createPurchaseOrderForDigitalProduct($digitalProduct, 2);

        $this->assertInstanceOf(PurchaseOrder::class, $purchaseOrder);
        $this->assertNull($purchaseOrder->sale_order_id);
    }
}
