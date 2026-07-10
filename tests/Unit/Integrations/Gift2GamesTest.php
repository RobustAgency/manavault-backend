<?php

namespace Tests\Unit\Integrations;

use Tests\TestCase;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use App\Models\Gift2GamesOrder;
use App\Integrations\Gift2Games;
use App\Models\PurchaseOrderItem;
use App\Enums\Gift2GamesOrderStatus;
use Illuminate\Support\Facades\Http;
use App\Models\PurchaseOrderSupplier;
use App\Enums\PurchaseOrderItemStatus;
use App\Enums\PurchaseOrderSupplierStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

class Gift2GamesTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    private DigitalProduct $product;

    private PurchaseOrder $purchaseOrder;

    private PurchaseOrderItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supplier = Supplier::factory()->create(['slug' => 'gift2games']);
        $this->product = DigitalProduct::factory()->forSupplier($this->supplier)->create([
            'sku' => '12345',
            'cost_price' => 10.00,
        ]);
        $this->purchaseOrder = PurchaseOrder::factory()->create([
            'order_number' => 'PO-TEST-001',
            'currency' => 'USD',
        ]);
        PurchaseOrderSupplier::factory()->create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseOrderSupplierStatus::PROCESSING->value,
            'transaction_id' => null,
        ]);
        $this->item = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'supplier_id' => $this->supplier->id,
            'digital_product_id' => $this->product->id,
            'quantity' => 1,
            'unit_cost' => 10.00,
            'subtotal' => 10.00,
            'transaction_id' => null,
            'status' => PurchaseOrderItemStatus::PENDING->value,
        ]);
    }

    private function makeIntegration(string $slug = 'gift2games'): Gift2Games
    {
        return app()->makeWith(Gift2Games::class, ['supplierSlug' => $slug]);
    }

    private function setupBatchForUpdateOrder(int $pendingCount = 1, int $fulfilledCount = 0): string
    {
        $batchNumber = 'batch_'.$this->item->id;

        $this->item->update([
            'transaction_id' => $batchNumber,
            'status' => PurchaseOrderItemStatus::PROCESSING->value,
        ]);

        for ($i = 0; $i < $pendingCount; $i++) {
            Gift2GamesOrder::create([
                'batch_number' => $batchNumber,
                'transaction_id' => null,
                'status' => Gift2GamesOrderStatus::PROCESSING,
            ]);
        }

        for ($i = 0; $i < $fulfilledCount; $i++) {
            Gift2GamesOrder::create([
                'batch_number' => $batchNumber,
                'transaction_id' => 'already-done-'.$i,
                'status' => Gift2GamesOrderStatus::FULFILLED,
            ]);
        }

        return $batchNumber;
    }

    /**
     * Fake the Gift2Games products endpoint used by the pre-order price check.
     */
    private function fakeProductPrice(float|string $price = 10.00): void
    {
        Http::fake([
            '*/products' => Http::response([
                'status' => 1,
                'data' => [[
                    'id' => '12345',
                    'title' => 'Test Product',
                    'sellPrice' => $price,
                    'price' => $price,
                    'inStock' => true,
                    'currency' => 'USD',
                ]],
                'metaData' => ['balance' => 25441.132, 'currency' => 'USD'],
            ], 200),
        ]);
    }

    // -------------------------------------------------------------------------
    // placeOrder
    // -------------------------------------------------------------------------

    public function test_place_order_creates_one_batch_record_per_unit(): void
    {
        Http::fake();

        $this->makeIntegration()->placeOrder($this->item);

        $this->assertDatabaseCount('gift2games_orders', 1);
        $this->assertDatabaseHas('gift2games_orders', [
            'batch_number' => 'batch_'.$this->item->id,
            'status' => Gift2GamesOrderStatus::PROCESSING->value,
        ]);
    }

    public function test_place_order_creates_one_record_per_unit_for_quantity_greater_than_one(): void
    {
        Http::fake();

        $this->item->update(['quantity' => 3]);

        $this->makeIntegration()->placeOrder($this->item->fresh());

        $this->assertDatabaseCount('gift2games_orders', 3);
        $this->assertEquals(
            3,
            Gift2GamesOrder::where('batch_number', 'batch_'.$this->item->id)->count()
        );
    }

    public function test_place_order_sets_item_transaction_id_to_batch_number(): void
    {
        Http::fake();

        $this->makeIntegration()->placeOrder($this->item);

        $this->assertEquals('batch_'.$this->item->id, $this->item->fresh()->transaction_id);
    }

    public function test_place_order_sets_item_status_to_processing(): void
    {
        Http::fake();

        $this->makeIntegration()->placeOrder($this->item);

        $this->assertEquals(PurchaseOrderItemStatus::PROCESSING, $this->item->fresh()->status);
    }

    public function test_place_order_makes_no_http_calls(): void
    {
        Http::fake();

        $this->makeIntegration()->placeOrder($this->item);

        Http::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // updateOrder
    // -------------------------------------------------------------------------

    public function test_update_order_creates_voucher_for_each_pending_batch_item(): void
    {
        $this->setupBatchForUpdateOrder(pendingCount: 1);
        $this->fakeProductPrice();

        Http::fake([
            '*/create_order' => Http::response(['status' => true, 'data' => ['orderId' => 'ORD-001', 'serialCode' => 'CODE-001', 'serialNumber' => 'SN-001']], 200),
        ]);

        $this->makeIntegration()->updateOrder($this->item->fresh());

        $this->assertDatabaseCount('vouchers', 1);
        $this->assertDatabaseHas('vouchers', [
            'purchase_order_id' => $this->purchaseOrder->id,
            'purchase_order_item_id' => $this->item->id,
            'serial_number' => 'SN-001',
            'status' => 'available',
        ]);
    }

    public function test_update_order_creates_one_voucher_per_pending_batch_item(): void
    {
        $this->item->update(['quantity' => 3]);
        $this->setupBatchForUpdateOrder(pendingCount: 3);
        $this->fakeProductPrice();

        Http::fake([
            '*/create_order' => Http::sequence()
                ->push(['status' => true, 'data' => ['orderId' => 'ORD-001', 'serialCode' => 'CODE-001', 'serialNumber' => 'SN-001']], 200)
                ->push(['status' => true, 'data' => ['orderId' => 'ORD-002', 'serialCode' => 'CODE-002', 'serialNumber' => 'SN-002']], 200)
                ->push(['status' => true, 'data' => ['orderId' => 'ORD-003', 'serialCode' => 'CODE-003', 'serialNumber' => 'SN-003']], 200),
        ]);

        $this->makeIntegration()->updateOrder($this->item->fresh());

        $this->assertDatabaseCount('vouchers', 3);
    }

    public function test_update_order_marks_batch_items_as_fulfilled(): void
    {
        $batchNumber = $this->setupBatchForUpdateOrder(pendingCount: 1);
        $this->fakeProductPrice();

        Http::fake([
            '*/create_order' => Http::response(['status' => true, 'data' => ['orderId' => 'ORD-001', 'serialCode' => 'CODE-001', 'serialNumber' => 'SN-001']], 200),
        ]);

        $this->makeIntegration()->updateOrder($this->item->fresh());

        $this->assertEquals(
            0,
            Gift2GamesOrder::where('batch_number', $batchNumber)
                ->where('status', '!=', Gift2GamesOrderStatus::FULFILLED)
                ->count()
        );
    }

    public function test_update_order_marks_item_fulfilled_when_all_batch_items_processed(): void
    {
        $this->setupBatchForUpdateOrder(pendingCount: 1);
        $this->fakeProductPrice();

        Http::fake([
            '*/create_order' => Http::response(['status' => true, 'data' => ['orderId' => 'ORD-001', 'serialCode' => 'CODE-001', 'serialNumber' => 'SN-001']], 200),
        ]);

        $this->makeIntegration()->updateOrder($this->item->fresh());

        $this->assertEquals(PurchaseOrderItemStatus::FULFILLED, $this->item->fresh()->status);
    }

    public function test_update_order_skips_already_fulfilled_batch_items_on_retry(): void
    {
        $this->item->update(['quantity' => 2]);
        $this->setupBatchForUpdateOrder(pendingCount: 1, fulfilledCount: 1);
        $this->fakeProductPrice();

        Http::fake([
            '*/create_order' => Http::response(['status' => true, 'data' => ['orderId' => 'ORD-NEW', 'serialCode' => 'CODE-NEW', 'serialNumber' => 'SN-NEW']], 200),
        ]);

        $this->makeIntegration()->updateOrder($this->item->fresh());

        $this->assertDatabaseCount('vouchers', 1);
        $this->assertCount(1, Http::recorded(fn ($r) => str_contains($r->url(), '/create_order')));
    }

    public function test_update_order_returns_early_on_api_error_without_creating_voucher(): void
    {
        $this->setupBatchForUpdateOrder(pendingCount: 1);
        $this->fakeProductPrice();

        Http::fake([
            '*/create_order' => Http::response(['status' => false, 'error' => ['message' => 'Product not found']], 200),
        ]);

        $this->makeIntegration()->updateOrder($this->item->fresh());

        $this->assertDatabaseCount('vouchers', 0);
    }

    public function test_update_order_leaves_item_processing_on_api_error(): void
    {
        $this->setupBatchForUpdateOrder(pendingCount: 1);
        $this->fakeProductPrice();

        Http::fake([
            '*/create_order' => Http::response(['status' => false, 'error' => ['message' => 'API error']], 200),
        ]);

        $this->makeIntegration()->updateOrder($this->item->fresh());

        $this->assertEquals(PurchaseOrderItemStatus::PROCESSING, $this->item->fresh()->status);
    }

    public function test_update_order_continues_processing_remaining_items_despite_partial_failure(): void
    {
        $this->item->update(['quantity' => 3]);
        $this->setupBatchForUpdateOrder(pendingCount: 3);
        $this->fakeProductPrice();

        Http::fake([
            '*/create_order' => Http::sequence()
                ->push(['status' => true, 'data' => ['orderId' => 'ORD-001', 'serialCode' => 'CODE-001', 'serialNumber' => 'SN-001']], 200)
                ->push(['status' => false, 'error' => ['message' => 'API error']], 200)
                ->push(['status' => true, 'data' => ['orderId' => 'ORD-003', 'serialCode' => 'CODE-003', 'serialNumber' => 'SN-003']], 200),
        ]);

        $this->makeIntegration()->updateOrder($this->item->fresh());

        // The failed slot is skipped; the other two succeed
        $this->assertDatabaseCount('vouchers', 2);
        // One order is still pending, so the item stays PROCESSING
        $this->assertEquals(PurchaseOrderItemStatus::PROCESSING, $this->item->fresh()->status);
    }

    public function test_update_order_processes_all_pending_orders_across_multiple_chunks(): void
    {
        $this->item->update(['quantity' => 6]);
        $this->setupBatchForUpdateOrder(pendingCount: 6);
        $this->fakeProductPrice();

        Http::fake([
            '*/create_order' => Http::response(['status' => true, 'data' => ['orderId' => 'ORD-001', 'serialCode' => 'CODE-001', 'serialNumber' => 'SN-001']], 200),
        ]);

        $this->makeIntegration()->updateOrder($this->item->fresh());

        // Both chunks (5 + 1) must be processed — all 6 vouchers created
        $this->assertDatabaseCount('vouchers', 6);
        $this->assertEquals(PurchaseOrderItemStatus::FULFILLED, $this->item->fresh()->status);
        $this->assertCount(6, Http::recorded(fn ($r) => str_contains($r->url(), '/create_order')));
    }

    // -------------------------------------------------------------------------
    // updateOrder — pre-order price check
    // -------------------------------------------------------------------------

    public function test_update_order_aborts_when_live_price_is_higher_than_unit_cost(): void
    {
        $this->setupBatchForUpdateOrder(pendingCount: 1);
        $this->fakeProductPrice(15.00); // item unit_cost is 10.00

        Http::fake([
            '*/create_order' => Http::response(['status' => true, 'data' => ['orderId' => 'ORD-001', 'serialCode' => 'CODE-001', 'serialNumber' => 'SN-001']], 200),
        ]);

        $this->makeIntegration()->updateOrder($this->item->fresh());

        $this->assertDatabaseCount('vouchers', 0);
        $this->assertEquals(PurchaseOrderItemStatus::PROCESSING, $this->item->fresh()->status);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/create_order'));
    }

    public function test_update_order_aborts_when_live_price_is_lower_than_unit_cost(): void
    {
        $this->setupBatchForUpdateOrder(pendingCount: 1);
        $this->fakeProductPrice(5.00); // item unit_cost is 10.00

        Http::fake([
            '*/create_order' => Http::response(['status' => true, 'data' => ['orderId' => 'ORD-001', 'serialCode' => 'CODE-001', 'serialNumber' => 'SN-001']], 200),
        ]);

        $this->makeIntegration()->updateOrder($this->item->fresh());

        $this->assertDatabaseCount('vouchers', 0);
        $this->assertEquals(PurchaseOrderItemStatus::PROCESSING, $this->item->fresh()->status);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/create_order'));
    }

    public function test_update_order_aborts_when_product_cannot_be_resolved(): void
    {
        $this->setupBatchForUpdateOrder(pendingCount: 1);

        Http::fake([
            '*/products' => Http::response(['status' => 0, 'data' => []], 200),
            '*/create_order' => Http::response(['status' => true, 'data' => ['orderId' => 'ORD-001', 'serialCode' => 'CODE-001', 'serialNumber' => 'SN-001']], 200),
        ]);

        $this->makeIntegration()->updateOrder($this->item->fresh());

        $this->assertDatabaseCount('vouchers', 0);
        $this->assertEquals(PurchaseOrderItemStatus::PROCESSING, $this->item->fresh()->status);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/create_order'));
    }

    public function test_update_order_proceeds_when_live_price_matches_unit_cost(): void
    {
        $this->setupBatchForUpdateOrder(pendingCount: 1);
        $this->fakeProductPrice(10.00); // matches item unit_cost

        Http::fake([
            '*/create_order' => Http::response(['status' => true, 'data' => ['orderId' => 'ORD-001', 'serialCode' => 'CODE-001', 'serialNumber' => 'SN-001']], 200),
        ]);

        $this->makeIntegration()->updateOrder($this->item->fresh());

        $this->assertDatabaseCount('vouchers', 1);
        $this->assertEquals(PurchaseOrderItemStatus::FULFILLED, $this->item->fresh()->status);
    }

    public function test_update_order_compares_against_unit_cost_not_drifted_product_cost_price(): void
    {
        // The product's cost_price has drifted (e.g. a later sync), but the item's
        // unit_cost — the price we committed to — is still 10.00.
        $this->product->update(['cost_price' => 20.00]);
        $this->setupBatchForUpdateOrder(pendingCount: 1);

        // Live price matches the committed unit_cost (10.00), not the drifted cost_price (20.00).
        $this->fakeProductPrice(10.00);

        Http::fake([
            '*/create_order' => Http::response(['status' => true, 'data' => ['orderId' => 'ORD-001', 'serialCode' => 'CODE-001', 'serialNumber' => 'SN-001']], 200),
        ]);

        $this->makeIntegration()->updateOrder($this->item->fresh());

        // Order proceeds because we compare against unit_cost, not the current cost_price.
        $this->assertDatabaseCount('vouchers', 1);
        $this->assertEquals(PurchaseOrderItemStatus::FULFILLED, $this->item->fresh()->status);
    }
}
