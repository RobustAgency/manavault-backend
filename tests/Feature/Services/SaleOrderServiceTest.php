<?php

namespace Tests\Feature\Services;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Voucher;
use App\Models\Supplier;
use App\Models\SaleOrder;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use App\Enums\SaleOrder\Status;
use App\Enums\VoucherCodeStatus;
use App\Models\PurchaseOrderItem;
use App\Events\SaleOrderCompleted;
use App\Services\SaleOrderService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Event;
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
     * Test: Insufficient internal stock leaves order in PROCESSING (no longer throws).
     */
    public function test_validates_sufficient_stock_is_available(): void
    {
        // Arrange: Create a product with an internal supplier and limited vouchers
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = Product::factory()->active()->create([
            'fulfillment_mode' => 'price',
        ]);

        $digitalProduct = DigitalProduct::factory()->forSupplier($supplier)->create([
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

        // Requesting more than available → order is created in PROCESSING (not thrown)
        $saleOrder = $this->service->createOrder([
            'order_number' => 'SO-001',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 3,
                ],
            ],
        ]);

        $this->assertEquals(Status::PROCESSING->value, $saleOrder->status);
        $this->assertDatabaseHas('sale_orders', ['order_number' => 'SO-001', 'status' => Status::PROCESSING->value]);
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
     * Test: No same voucher is assigned twice — second order is left PROCESSING.
     */
    public function test_same_voucher_cannot_be_assigned_twice(): void
    {
        // Arrange: Create product with internal supplier and limited vouchers
        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = Product::factory()->active()->create([
            'fulfillment_mode' => 'price',
        ]);

        $digitalProduct = DigitalProduct::factory()->forSupplier($supplier)->create([
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

        // Act: Create first sale order consuming 1 voucher
        $saleOrder1 = $this->service->createOrder([
            'order_number' => 'SO-007A',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);
        $this->assertEquals(Status::COMPLETED->value, $saleOrder1->status);

        // Act: Create second sale order requesting 2 units — only 1 remains → PROCESSING
        $saleOrder2 = $this->service->createOrder([
            'order_number' => 'SO-007B',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);
        $this->assertEquals(Status::PROCESSING->value, $saleOrder2->status);

        // The single remaining voucher must not be allocated to SO-007B (0 available after allocating 1)
        $allocatedToSo2 = $saleOrder2->items->first()->digitalProducts()->count();
        $this->assertLessThan(2, $allocatedToSo2);
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

    // -------------------------------------------------------------------------
    // Auto-PO scenarios
    // -------------------------------------------------------------------------

    /**
     * Sufficient internal stock → order COMPLETED, no PO created.
     */
    public function test_sufficient_internal_stock_completes_order_without_purchase_order(): void
    {
        Event::fake([SaleOrderCompleted::class]);

        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = Product::factory()->active()->create(['fulfillment_mode' => 'price']);
        $digitalProduct = DigitalProduct::factory()->forSupplier($supplier)->create(['selling_price' => 10.00]);
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $po = PurchaseOrder::factory()->completed()->create();
        $poi = PurchaseOrderItem::factory()->forPurchaseOrder($po)->forDigitalProduct($digitalProduct)->withQuantity(3)->create();
        Voucher::factory()->count(3)->create([
            'purchase_order_id' => $po->id,
            'purchase_order_item_id' => $poi->id,
            'status' => VoucherCodeStatus::AVAILABLE->value,
        ]);

        $saleOrder = $this->service->createOrder([
            'order_number' => 'SO-AUTO-001',
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ]);

        $this->assertEquals(Status::COMPLETED->value, $saleOrder->status);
        $this->assertEquals(0, PurchaseOrder::where('order_number', 'like', 'PO-%')->whereDoesntHave('items', fn ($q) => $q->where('purchase_order_id', $po->id))->count());
        Event::assertDispatched(SaleOrderCompleted::class);
    }

    /**
     * Insufficient internal stock (no external supplier) → order PROCESSING, partial allocation.
     */
    public function test_insufficient_internal_stock_creates_processing_order(): void
    {
        Event::fake([SaleOrderCompleted::class]);

        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = Product::factory()->active()->create(['fulfillment_mode' => 'price']);
        $digitalProduct = DigitalProduct::factory()->forSupplier($supplier)->create(['selling_price' => 10.00]);
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $po = PurchaseOrder::factory()->completed()->create();
        $poi = PurchaseOrderItem::factory()->forPurchaseOrder($po)->forDigitalProduct($digitalProduct)->withQuantity(2)->create();
        Voucher::factory()->count(2)->create([
            'purchase_order_id' => $po->id,
            'purchase_order_item_id' => $poi->id,
            'status' => VoucherCodeStatus::AVAILABLE->value,
        ]);

        $saleOrder = $this->service->createOrder([
            'order_number' => 'SO-AUTO-002',
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ]);

        $this->assertEquals(Status::PROCESSING->value, $saleOrder->status);
        Event::assertNotDispatched(SaleOrderCompleted::class);
    }

    /**
     * Shortfall with Gift2Games supplier → auto-PO created, vouchers allocated, order COMPLETED.
     */
    public function test_shortfall_with_gift2games_supplier_creates_po_and_completes_order(): void
    {
        Event::fake([SaleOrderCompleted::class]);

        $supplier = Supplier::factory()->create([
            'slug' => 'gift2games',
            'type' => 'external',
        ]);
        $product = Product::factory()->active()->create(['fulfillment_mode' => 'price']);
        $digitalProduct = DigitalProduct::factory()->forSupplier($supplier)->create([
            'sku' => 'G2G-SKU-001',
            'cost_price' => 10.00,
            'selling_price' => 15.00,
        ]);
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        // 0 vouchers available — full shortfall
        $purchaseOrderCount = PurchaseOrder::count();

        Http::fake([
            '*/create_order' => Http::response([
                'status' => 'success',
                'data' => [
                    'referenceNumber' => 'REF-G2G-001',
                    'productId' => 99,
                    'code' => 'VOUCHER-001',
                    'pin' => '0000',
                    'serial' => 'SER-001',
                    'expiryDate' => '2026-12-31',
                ],
            ], 200),
        ]);

        $saleOrder = $this->service->createOrder([
            'order_number' => 'SO-AUTO-003',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        // A new PO was created
        $this->assertGreaterThan($purchaseOrderCount, PurchaseOrder::count());
        // Voucher was stored and allocated → order is COMPLETED
        $this->assertEquals(Status::COMPLETED->value, $saleOrder->status);
        Event::assertDispatched(SaleOrderCompleted::class);
    }

    /**
     * Shortfall with Ezcards supplier → auto-PO created, order stays PROCESSING.
     */
    public function test_shortfall_with_ezcards_supplier_creates_po_and_leaves_order_processing(): void
    {
        Event::fake([SaleOrderCompleted::class]);

        $supplier = Supplier::factory()->create([
            'slug' => 'ez_cards',
            'type' => 'external',
        ]);
        $product = Product::factory()->active()->create(['fulfillment_mode' => 'price']);
        $digitalProduct = DigitalProduct::factory()->forSupplier($supplier)->create([
            'sku' => 'EZC-SKU-001',
            'cost_price' => 10.00,
            'selling_price' => 15.00,
        ]);
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $purchaseOrderCount = PurchaseOrder::count();

        Http::fake([
            '*/v2/orders' => Http::response([
                'requestId' => 'req-001',
                'data' => [
                    'transactionId' => '9999',
                    'clientOrderNumber' => 'PO-EZC-001',
                    'status' => 'PROCESSING',
                    'grandTotal' => ['amount' => '10.00', 'currency' => 'USD'],
                    'products' => [
                        ['sku' => 'EZC-SKU-001', 'quantity' => 1, 'status' => 'PROCESSING'],
                    ],
                ],
            ], 200),
        ]);

        $saleOrder = $this->service->createOrder([
            'order_number' => 'SO-AUTO-004',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        // PO was created
        $this->assertGreaterThan($purchaseOrderCount, PurchaseOrder::count());
        // No vouchers yet (async) → order stays PROCESSING
        $this->assertEquals(Status::PROCESSING->value, $saleOrder->status);
        Event::assertNotDispatched(SaleOrderCompleted::class);
    }
}
