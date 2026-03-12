<?php

namespace Tests\Feature\Services;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Voucher;
use App\Models\SaleOrder;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use App\Enums\SaleOrder\Status;
use App\Enums\VoucherCodeStatus;
use App\Models\PurchaseOrderItem;
use App\Services\SaleOrderService;
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

    /**
     * Test: System validates that sufficient stock is available.
     */
    public function test_validates_sufficient_stock_is_available(): void
    {
        // Arrange: Create a product with a digital product and limited vouchers
        $product = Product::factory()->active()->create([
            'fulfillment_mode' => 'price',
        ]);

        $digitalProduct = DigitalProduct::factory()->create([
            'selling_price' => 100.00,
        ]);
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $purchaseOrder = PurchaseOrder::factory()->completed()->create();
        $purchaseOrderItem = PurchaseOrderItem::factory()
            ->forPurchaseOrder($purchaseOrder)
            ->forDigitalProduct($digitalProduct)
            ->withQuantity(2)
            ->create();

        Voucher::factory()->count(2)->create([
            'purchase_order_id' => $purchaseOrder->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'status' => VoucherCodeStatus::AVAILABLE->value,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient inventory for product');

        $this->service->createOrder([
            'order_number' => 'SO-001',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 3, // Request more than available
                ],
            ],
        ]);
    }

    /**
     * Test: System allows order when sufficient stock is available.
     */
    public function test_allows_order_when_sufficient_stock_available(): void
    {
        // Arrange: Create a product with sufficient vouchers
        $product = Product::factory()->active()->create([
            'fulfillment_mode' => 'price',
        ]);

        $digitalProduct = DigitalProduct::factory()->create([
            'selling_price' => 50.00,
        ]);
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $purchaseOrder = PurchaseOrder::factory()->completed()->create();
        $purchaseOrderItem = PurchaseOrderItem::factory()
            ->forPurchaseOrder($purchaseOrder)
            ->forDigitalProduct($digitalProduct)
            ->withQuantity(5)
            ->create();

        Voucher::factory()->count(5)->create([
            'purchase_order_id' => $purchaseOrder->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'status' => VoucherCodeStatus::AVAILABLE->value,
        ]);

        $saleOrder = $this->service->createOrder([
            'order_number' => 'SO-002',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 3,
                ],
            ],
        ]);

        $this->assertNotNull($saleOrder->id);
        $this->assertEquals(Status::COMPLETED->value, $saleOrder->status);
    }

    /**
     * Test: Exact voucher code is assigned to the corresponding product.
     */
    public function test_exact_voucher_code_assigned_to_product(): void
    {
        // Arrange: Create product with digital product and vouchers
        $product = Product::factory()->active()->create([
            'fulfillment_mode' => 'price',
        ]);

        $digitalProduct = DigitalProduct::factory()->create([
            'selling_price' => 25.00,
        ]);
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $purchaseOrder = PurchaseOrder::factory()->completed()->create();
        $purchaseOrderItem = PurchaseOrderItem::factory()
            ->forPurchaseOrder($purchaseOrder)
            ->forDigitalProduct($digitalProduct)
            ->withQuantity(2)
            ->create();

        $vouchers = Voucher::factory()->count(2)->create([
            'purchase_order_id' => $purchaseOrder->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'status' => VoucherCodeStatus::AVAILABLE->value,
        ]);

        // Act: Create sale order
        $saleOrder = $this->service->createOrder([
            'order_number' => 'SO-003',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        // Assert: Vouchers are correctly linked to the digital product
        $allocations = $saleOrder->items->first()->digitalProducts()->get();
        $this->assertCount(2, $allocations);

        foreach ($allocations as $allocation) {
            $this->assertEquals($digitalProduct->id, $allocation->digital_product_id);
            $this->assertNotNull($allocation->voucher_id);
            $this->assertTrue($vouchers->pluck('id')->contains($allocation->voucher_id));
        }
    }

    /**
     * Test: Purchase order remains unchanged and is not updated.
     */
    public function test_purchase_order_remains_unchanged(): void
    {
        // Arrange: Create product and purchase order with vouchers
        $product = Product::factory()->active()->create([
            'fulfillment_mode' => 'price',
        ]);

        $digitalProduct = DigitalProduct::factory()->create([
            'selling_price' => 75.00,
        ]);
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $purchaseOrder = PurchaseOrder::factory()->completed()->create();
        $originalStatus = $purchaseOrder->status;
        $originalPrice = $purchaseOrder->total_price;

        $purchaseOrderItem = PurchaseOrderItem::factory()
            ->forPurchaseOrder($purchaseOrder)
            ->forDigitalProduct($digitalProduct)
            ->withQuantity(3)
            ->create();

        Voucher::factory()->count(3)->create([
            'purchase_order_id' => $purchaseOrder->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'status' => VoucherCodeStatus::AVAILABLE->value,
        ]);

        // Act: Create sale order
        $this->service->createOrder([
            'order_number' => 'SO-004',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        // Assert: Purchase order remains unchanged
        $purchaseOrder->refresh();
        $this->assertEquals($originalStatus, $purchaseOrder->status);
        $this->assertEquals($originalPrice, $purchaseOrder->total_price);
    }

    /**
     * Test: Sales order is created successfully.
     */
    public function test_sale_order_created_successfully(): void
    {
        // Arrange: Create product with sufficient vouchers
        $product1 = Product::factory()->active()->create([
            'fulfillment_mode' => 'price',
        ]);

        $product2 = Product::factory()->active()->create([
            'fulfillment_mode' => 'price',
        ]);

        // Setup digital products and vouchers for product1
        $digitalProduct1 = DigitalProduct::factory()->create([
            'selling_price' => 50.00,
        ]);
        $product1->digitalProducts()->attach($digitalProduct1->id, ['priority' => 1]);

        $purchaseOrder1 = PurchaseOrder::factory()->completed()->create();
        $purchaseOrderItem1 = PurchaseOrderItem::factory()
            ->forPurchaseOrder($purchaseOrder1)
            ->forDigitalProduct($digitalProduct1)
            ->withQuantity(3)
            ->create();

        Voucher::factory()->count(3)->create([
            'purchase_order_id' => $purchaseOrder1->id,
            'purchase_order_item_id' => $purchaseOrderItem1->id,
            'status' => VoucherCodeStatus::AVAILABLE->value,
        ]);

        // Setup digital products and vouchers for product2
        $digitalProduct2 = DigitalProduct::factory()->create([
            'selling_price' => 75.00,
        ]);
        $product2->digitalProducts()->attach($digitalProduct2->id, ['priority' => 1]);

        $purchaseOrder2 = PurchaseOrder::factory()->completed()->create();
        $purchaseOrderItem2 = PurchaseOrderItem::factory()
            ->forPurchaseOrder($purchaseOrder2)
            ->forDigitalProduct($digitalProduct2)
            ->withQuantity(2)
            ->create();

        Voucher::factory()->count(2)->create([
            'purchase_order_id' => $purchaseOrder2->id,
            'purchase_order_item_id' => $purchaseOrderItem2->id,
            'status' => VoucherCodeStatus::AVAILABLE->value,
        ]);

        // Act: Create sale order with multiple items
        $saleOrder = $this->service->createOrder([
            'order_number' => 'SO-005',
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
        ]);

        // Assert: Sale order created with correct structure
        $this->assertNotNull($saleOrder->id);
        $this->assertEquals('SO-005', $saleOrder->order_number);
        $this->assertEquals(Status::COMPLETED->value, $saleOrder->status);
        $this->assertEquals(SaleOrder::MANASTORE, $saleOrder->source);
        $this->assertCount(2, $saleOrder->items);

        // Verify pricing calculation
        $expectedTotal = (2 * 50.00) + (1 * 75.00);
        $this->assertEquals($expectedTotal, $saleOrder->total_price);

        // Verify items
        $item1 = $saleOrder->items->firstWhere('product_id', $product1->id);
        $this->assertEquals(2, $item1->quantity);
        $this->assertEquals(50.00, $item1->unit_price);
        $this->assertEquals(100.00, $item1->subtotal);

        $item2 = $saleOrder->items->firstWhere('product_id', $product2->id);
        $this->assertEquals(1, $item2->quantity);
        $this->assertEquals(75.00, $item2->unit_price);
        $this->assertEquals(75.00, $item2->subtotal);
    }

    /**
     * Test: No same voucher is assigned twice (unique constraint).
     */
    public function test_same_voucher_cannot_be_assigned_twice(): void
    {
        // Arrange: Create product with limited vouchers
        $product = Product::factory()->active()->create([
            'fulfillment_mode' => 'price',
        ]);

        $digitalProduct = DigitalProduct::factory()->create([
            'selling_price' => 50.00,
        ]);
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $purchaseOrder = PurchaseOrder::factory()->completed()->create();
        $purchaseOrderItem = PurchaseOrderItem::factory()
            ->forPurchaseOrder($purchaseOrder)
            ->forDigitalProduct($digitalProduct)
            ->withQuantity(2)
            ->create();

        Voucher::factory()->count(2)->create([
            'purchase_order_id' => $purchaseOrder->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'status' => VoucherCodeStatus::AVAILABLE->value,
        ]);

        // Act: Create first sale order
        $saleOrder1 = $this->service->createOrder([
            'order_number' => 'SO-007A',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        // Assert: First order succeeds
        $this->assertNotNull($saleOrder1->id);

        // Act & Assert: Attempting to create another order should fail
        // because remaining voucher is insufficient for 2 units
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient inventory for product');

        $this->service->createOrder([
            'order_number' => 'SO-007B',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2, // Only 1 voucher left
                ],
            ],
        ]);
    }

    /**
     * Test: Transaction is rolled back on exception.
     */
    public function test_transaction_rolled_back_on_exception(): void
    {
        // Arrange: Create product without digital products (will trigger exception)
        $product = Product::factory()->active()->create();

        // Act & Assert: Should throw exception and rollback
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('has no digital products assigned');

        $this->service->createOrder([
            'order_number' => 'SO-008',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        // Verify no sale order was created
        $this->assertFalse(
            SaleOrder::where('order_number', 'SO-008')->exists()
        );
    }

    /**
     * Test: Fulfillment mode PRICE orders by lowest cost.
     */
    public function test_price_fulfillment_mode_orders_by_cost(): void
    {
        // Arrange: Create product with PRICE fulfillment mode
        $product = Product::factory()->active()->create([
            'fulfillment_mode' => 'price',
        ]);

        // Create digital products with different costs
        $digitalProduct1 = DigitalProduct::factory()->create(['cost_price' => 50.00, 'selling_price' => 100.00]);
        $digitalProduct2 = DigitalProduct::factory()->create(['cost_price' => 30.00, 'selling_price' => 60.00]);
        $digitalProduct3 = DigitalProduct::factory()->create(['cost_price' => 70.00, 'selling_price' => 140.00]);

        $product->digitalProducts()->attach([
            $digitalProduct1->id => ['priority' => 3],
            $digitalProduct2->id => ['priority' => 2],
            $digitalProduct3->id => ['priority' => 1],
        ]);

        // Create vouchers for each digital product
        foreach ([$digitalProduct1, $digitalProduct2, $digitalProduct3] as $dp) {
            $po = PurchaseOrder::factory()->completed()->create();
            $poi = PurchaseOrderItem::factory()
                ->forPurchaseOrder($po)
                ->forDigitalProduct($dp)
                ->withQuantity(2)
                ->create();

            Voucher::factory()->count(2)->create([
                'purchase_order_id' => $po->id,
                'purchase_order_item_id' => $poi->id,
                'status' => VoucherCodeStatus::AVAILABLE->value,
            ]);
        }

        // Act: Create sale order
        $saleOrder = $this->service->createOrder([
            'order_number' => 'SO-009',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        // Assert: Vouchers are allocated from lowest cost first (digitalProduct2)
        $allocations = $saleOrder->items->first()->digitalProducts()->get();
        $this->assertEquals($digitalProduct2->id, $allocations->first()->digital_product_id);
    }

    /**
     * Test: Fulfillment mode MANUAL orders by priority.
     */
    public function test_manual_fulfillment_mode_orders_by_priority(): void
    {
        // Arrange: Create product with MANUAL fulfillment mode
        $product = Product::factory()->active()->create([
            'fulfillment_mode' => 'manual',
        ]);

        // Create digital products
        $digitalProduct1 = DigitalProduct::factory()->create([
            'cost_price' => 50.00,
            'selling_price' => 100.00,
        ]);
        $digitalProduct2 = DigitalProduct::factory()->create([
            'cost_price' => 30.00,
            'selling_price' => 60.00,
        ]);
        $digitalProduct3 = DigitalProduct::factory()->create([
            'cost_price' => 70.00,
            'selling_price' => 140.00,
        ]);

        $product->digitalProducts()->attach([
            $digitalProduct1->id => ['priority' => 3],
            $digitalProduct2->id => ['priority' => 1], // Highest priority
            $digitalProduct3->id => ['priority' => 2],
        ]);

        // Create vouchers for each digital product
        foreach ([$digitalProduct1, $digitalProduct2, $digitalProduct3] as $dp) {
            $po = PurchaseOrder::factory()->completed()->create();
            $poi = PurchaseOrderItem::factory()
                ->forPurchaseOrder($po)
                ->forDigitalProduct($dp)
                ->withQuantity(2)
                ->create();

            Voucher::factory()->count(2)->create([
                'purchase_order_id' => $po->id,
                'purchase_order_item_id' => $poi->id,
                'status' => VoucherCodeStatus::AVAILABLE->value,
            ]);
        }

        // Act: Create sale order
        $saleOrder = $this->service->createOrder([
            'order_number' => 'SO-010',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        $allocations = $saleOrder->items->first()->digitalProducts()->get();
        $this->assertEquals($digitalProduct2->id, $allocations->first()->digital_product_id);
    }
}
