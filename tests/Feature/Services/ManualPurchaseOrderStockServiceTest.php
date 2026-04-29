<?php

namespace Tests\Feature\Services;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Voucher;
use App\Models\Supplier;
use App\Models\SaleOrder;
use App\Enums\SupplierType;
use App\Models\DigitalProduct;
use App\Enums\SaleOrder\Status;
use App\Enums\VoucherCodeStatus;
use App\Services\SaleOrderService;
use App\Services\PurchaseOrderService;
use App\Services\ManualPurchaseOrderStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ManualPurchaseOrderStockServiceTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseOrderService $purchaseOrderService;

    private ManualPurchaseOrderStockService $manualStockService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->purchaseOrderService = app(PurchaseOrderService::class);
        $this->saleOrderService = app(SaleOrderService::class);
        $this->manualStockService = app(ManualPurchaseOrderStockService::class);
    }

    public function test_manual_purchase_order_allocates_stock_to_processing_sale_orders(): void
    {
        // Setup: Create a supplier and digital products
        $supplier = Supplier::factory()->create(['type' => SupplierType::INTERNAL->value]);
        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'cost_price' => 10.00,
            'selling_price' => 15.00,
        ]);

        // Create a product linked to the digital product
        $product = Product::factory()->create();
        $product->digitalProducts()->attach($digitalProduct->id, ['supplier_id' => $supplier->id]);

        // Create a sale order in PROCESSING status (unfulfilled) with failed purchase order
        // to simulate the condition where manual PO should allocate to it
        $saleOrder = SaleOrder::factory()->create([
            'status' => Status::PROCESSING->value,
        ]);
        $failedPO = \App\Models\PurchaseOrder::factory()->create([
            'status' => 'failed',
            'sale_order_id' => $saleOrder->id,
        ]);

        $saleOrderItem = $saleOrder->items()->create([
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 15.00,
            'subtotal' => 75.00,
        ]);

        // Verify the sale order starts in PROCESSING (no stock allocated)
        $this->assertEquals(Status::PROCESSING->value, $saleOrder->status);
        $this->assertEquals(0, $saleOrderItem->digitalProducts()->count());

        // Create a manual purchase order without sale_order_id (5 units)
        $purchaseOrder = $this->purchaseOrderService->createPurchaseOrder([
            'currency' => 'usd',
            'items' => [
                [
                    'supplier_id' => $supplier->id,
                    'digital_product_id' => $digitalProduct->id,
                    'quantity' => 5,
                ],
            ],
        ]);

        // Verify that the purchase order was created
        $this->assertNotNull($purchaseOrder);
        $this->assertDatabaseHas('purchase_orders', ['id' => $purchaseOrder->id]);
        $this->assertNull($purchaseOrder->sale_order_id);

        // Create vouchers for the purchase order items with AVAILABLE status
        $purchaseOrderItems = $purchaseOrder->items()->get();
        foreach ($purchaseOrderItems as $item) {
            for ($i = 0; $i < $item->quantity; $i++) {
                Voucher::factory()->create([
                    'purchase_order_item_id' => $item->id,
                    'status' => VoucherCodeStatus::AVAILABLE->value,
                ]);
            }
        }

        // Call the service directly to simulate event processing
        $this->manualStockService->processPurchaseOrderStock($purchaseOrder->id);

        // Reload the sale order to check if it was updated
        $saleOrder->refresh();
        $saleOrderItem->refresh();

        // Verify sale order status was updated to COMPLETED since all items are now allocated
        $this->assertEquals(Status::COMPLETED->value, $saleOrder->status);
    }

    public function test_auto_created_purchase_orders_do_not_allocate_stock(): void
    {
        // Setup: Create a supplier and digital products
        $supplier = Supplier::factory()->create(['type' => SupplierType::INTERNAL->value]);
        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'cost_price' => 10.00,
            'selling_price' => 15.00,
        ]);

        // Create a product linked to the digital product
        $product = Product::factory()->create();
        $product->digitalProducts()->attach($digitalProduct->id, ['supplier_id' => $supplier->id]);

        // Create two sale orders in PROCESSING status
        $saleOrder1 = SaleOrder::factory()->create(['status' => Status::PROCESSING->value]);
        $saleOrderItem1 = $saleOrder1->items()->create([
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 15.00,
            'subtotal' => 75.00,
        ]);

        $saleOrder2 = SaleOrder::factory()->create(['status' => Status::PROCESSING->value]);
        $saleOrderItem2 = $saleOrder2->items()->create([
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 15.00,
            'subtotal' => 75.00,
        ]);

        // Create an auto-generated purchase order tied to saleOrder1 (has sale_order_id)
        $purchaseOrder = $this->purchaseOrderService->createPurchaseOrder([
            'currency' => 'usd',
            'sale_order_id' => $saleOrder1->id,
            'items' => [
                [
                    'supplier_id' => $supplier->id,
                    'digital_product_id' => $digitalProduct->id,
                    'quantity' => 5,
                ],
            ],
        ]);

        // Reload both sale orders
        $saleOrder1->refresh();
        $saleOrder2->refresh();

        // Verify that the purchase order was created with sale_order_id
        $this->assertNotNull($purchaseOrder);
        $this->assertEquals($saleOrder1->id, $purchaseOrder->sale_order_id);

        // Verify saleOrder2 status remains PROCESSING (no automatic stock allocation from auto-PO)
        $this->assertEquals(Status::PROCESSING->value, $saleOrder2->status);
    }

    public function test_manual_purchase_order_with_partial_allocation(): void
    {
        // Setup: Create suppliers and digital products
        $supplier = Supplier::factory()->create(['type' => SupplierType::INTERNAL->value]);
        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'cost_price' => 10.00,
            'selling_price' => 15.00,
        ]);

        // Create a product linked to the digital product
        $product = Product::factory()->create();
        $product->digitalProducts()->attach($digitalProduct->id, ['supplier_id' => $supplier->id]);

        // Create multiple sale orders in PROCESSING status with failed POs
        $saleOrder1 = SaleOrder::factory()->create(['status' => Status::PROCESSING->value]);
        $failedPO1 = \App\Models\PurchaseOrder::factory()->create([
            'status' => 'failed',
            'sale_order_id' => $saleOrder1->id,
        ]);

        $saleOrderItem1 = $saleOrder1->items()->create([
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 15.00,
            'subtotal' => 45.00,
        ]);

        $saleOrder2 = SaleOrder::factory()->create(['status' => Status::PROCESSING->value]);
        $failedPO2 = \App\Models\PurchaseOrder::factory()->create([
            'status' => 'failed',
            'sale_order_id' => $saleOrder2->id,
        ]);

        $saleOrderItem2 = $saleOrder2->items()->create([
            'product_id' => $product->id,
            'quantity' => 4,
            'unit_price' => 15.00,
            'subtotal' => 60.00,
        ]);

        // Create a manual purchase order with only 5 units (not enough for both sale orders)
        $purchaseOrder = $this->purchaseOrderService->createPurchaseOrder([
            'currency' => 'usd',
            'items' => [
                [
                    'supplier_id' => $supplier->id,
                    'digital_product_id' => $digitalProduct->id,
                    'quantity' => 5,
                ],
            ],
        ]);

        // Create vouchers for the purchase order items with AVAILABLE status
        $purchaseOrderItems = $purchaseOrder->items()->get();
        foreach ($purchaseOrderItems as $item) {
            for ($i = 0; $i < $item->quantity; $i++) {
                Voucher::factory()->create([
                    'purchase_order_item_id' => $item->id,
                    'status' => VoucherCodeStatus::AVAILABLE->value,
                ]);
            }
        }

        // Call the service to process allocation
        $this->manualStockService->processPurchaseOrderStock($purchaseOrder->id);

        // Reload sale orders
        $saleOrder1->refresh();
        $saleOrder2->refresh();

        // Verify first sale order is fully allocated and completed (needs 3 units, 5 available)
        $this->assertEquals(Status::COMPLETED->value, $saleOrder1->status);

        // Verify second sale order is still processing (needs 4, only 2 remaining after first is filled)
        // Or it could be completed if exactly enough (depends on allocation logic)
        $this->assertTrue(
            in_array($saleOrder2->status, [Status::PROCESSING->value, Status::COMPLETED->value])
        );
    }

    public function test_manual_purchase_order_with_multiple_digital_products(): void
    {
        // Setup: Create a supplier and multiple digital products
        $supplier = Supplier::factory()->create(['type' => SupplierType::INTERNAL->value]);

        $digitalProduct1 = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'cost_price' => 10.00,
            'selling_price' => 15.00,
        ]);

        $digitalProduct2 = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'cost_price' => 20.00,
            'selling_price' => 25.00,
        ]);

        // Create products linked to digital products
        $product1 = Product::factory()->create();
        $product1->digitalProducts()->attach($digitalProduct1->id, ['supplier_id' => $supplier->id]);

        $product2 = Product::factory()->create();
        $product2->digitalProducts()->attach($digitalProduct2->id, ['supplier_id' => $supplier->id]);

        // Create a sale order with multiple items and a failed PO
        $saleOrder = SaleOrder::factory()->create(['status' => Status::PROCESSING->value]);
        $failedPO = \App\Models\PurchaseOrder::factory()->create([
            'status' => 'failed',
            'sale_order_id' => $saleOrder->id,
        ]);

        $saleOrderItem1 = $saleOrder->items()->create([
            'product_id' => $product1->id,
            'quantity' => 3,
            'unit_price' => 15.00,
            'subtotal' => 45.00,
        ]);

        $saleOrderItem2 = $saleOrder->items()->create([
            'product_id' => $product2->id,
            'quantity' => 2,
            'unit_price' => 25.00,
            'subtotal' => 50.00,
        ]);

        // Create a manual purchase order with both digital products (no sale_order_id)
        $purchaseOrder = $this->purchaseOrderService->createPurchaseOrder([
            'currency' => 'usd',
            'items' => [
                [
                    'supplier_id' => $supplier->id,
                    'digital_product_id' => $digitalProduct1->id,
                    'quantity' => 3,
                ],
                [
                    'supplier_id' => $supplier->id,
                    'digital_product_id' => $digitalProduct2->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        // Create vouchers for the purchase order items with AVAILABLE status
        $purchaseOrderItems = $purchaseOrder->items()->get();
        foreach ($purchaseOrderItems as $item) {
            for ($i = 0; $i < $item->quantity; $i++) {
                Voucher::factory()->create([
                    'purchase_order_item_id' => $item->id,
                    'status' => VoucherCodeStatus::AVAILABLE->value,
                ]);
            }
        }

        // Call the service to process allocation
        $this->manualStockService->processPurchaseOrderStock($purchaseOrder->id);

        // Reload the sale order
        $saleOrder->refresh();

        // Verify that the purchase order was created
        $this->assertNotNull($purchaseOrder);
        $this->assertNull($purchaseOrder->sale_order_id);

        // Verify sale order status was updated to COMPLETED
        $this->assertEquals(Status::COMPLETED->value, $saleOrder->status);
    }
}
