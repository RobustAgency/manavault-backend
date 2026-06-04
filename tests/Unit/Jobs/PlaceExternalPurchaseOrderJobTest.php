<?php

namespace Tests\Unit\Jobs;

use Tests\TestCase;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\Http;
use App\Models\PurchaseOrderSupplier;
use App\Enums\PurchaseOrderItemStatus;
use App\Enums\PurchaseOrderSupplierStatus;
use App\Jobs\PlaceExternalPurchaseOrderJob;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PlaceExternalPurchaseOrderJobTest extends TestCase
{
    use RefreshDatabase;

    private function createOrderSetup(string $supplierSlug, string $sku = 'SKU-001', float $subtotal = 50.0): array
    {
        $supplier = Supplier::factory()->create(['slug' => $supplierSlug]);
        $product = DigitalProduct::factory()->forSupplier($supplier)->create(['sku' => $sku]);
        $purchaseOrder = PurchaseOrder::factory()->create(['currency' => 'USD', 'order_number' => 'PO-TEST-001']);
        $pos = PurchaseOrderSupplier::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderSupplierStatus::PROCESSING->value,
            'transaction_id' => null,
        ]);
        $item = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'supplier_id' => $supplier->id,
            'digital_product_id' => $product->id,
            'quantity' => 1,
            'unit_cost' => $subtotal,
            'subtotal' => $subtotal,
            'transaction_id' => null,
            'status' => PurchaseOrderItemStatus::PENDING->value,
        ]);

        return compact('supplier', 'product', 'purchaseOrder', 'pos', 'item');
    }

    private function dispatchJob(array $setup, array $extraItems = []): void
    {
        $items = array_merge([$setup['item']], $extraItems);
        $job = new PlaceExternalPurchaseOrderJob(
            $setup['purchaseOrder'],
            $setup['supplier'],
            $setup['pos'],
            $items,
            $setup['purchaseOrder']->order_number,
            'USD',
        );
        app()->call([$job, 'handle']);
    }

    // -------------------------------------------------------------------------
    // EzCards (new-style integration)
    // -------------------------------------------------------------------------

    public function test_places_order_for_ez_cards_via_integration(): void
    {
        $setup = $this->createOrderSetup('ez_cards', 'EZC-001');

        Http::fake([
            '*/v2/orders' => Http::response([
                'data' => ['transactionId' => '555', 'status' => 'PROCESSING'],
            ], 200),
        ]);

        $this->dispatchJob($setup);

        $fresh = $setup['item']->fresh();
        $this->assertEquals('555', $fresh->transaction_id);
        $this->assertEquals(PurchaseOrderItemStatus::PROCESSING, $fresh->status);
    }

    public function test_ez_cards_continues_processing_remaining_items_when_one_fails(): void
    {
        $setup = $this->createOrderSetup('ez_cards', 'EZC-001');

        $item2 = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $setup['purchaseOrder']->id,
            'supplier_id' => $setup['supplier']->id,
            'digital_product_id' => $setup['product']->id,
            'quantity' => 1,
            'unit_cost' => 10.00,
            'subtotal' => 10.00,
            'transaction_id' => null,
            'status' => PurchaseOrderItemStatus::PENDING->value,
        ]);

        Http::fake([
            '*/v2/orders' => Http::sequence()
                ->push(['error' => 'Bad Request'], 400)
                ->push(['data' => ['transactionId' => '777', 'status' => 'PROCESSING']], 200),
        ]);

        $this->dispatchJob($setup, [$item2]);

        $this->assertNull($setup['item']->fresh()->transaction_id);
        $this->assertEquals('777', $item2->fresh()->transaction_id);
    }

    // -------------------------------------------------------------------------
    // Gift2Games (legacy path)
    // -------------------------------------------------------------------------

    public function test_places_order_and_stores_vouchers_for_gift2games(): void
    {
        $setup = $this->createOrderSetup('gift2games', '12345');

        Http::fake([
            '*/check_balance' => Http::response([
                'status' => true,
                'data' => ['userBalance' => '500.00'],
            ], 200),
            '*/create_order' => Http::response([
                'status' => true,
                'data' => ['serialCode' => 'GCODE-001', 'serialNumber' => 'GSN-001'],
            ], 200),
        ]);

        $this->dispatchJob($setup);

        $this->assertDatabaseCount('vouchers', 1);
        $this->assertDatabaseHas('vouchers', [
            'purchase_order_id' => $setup['purchaseOrder']->id,
            'purchase_order_item_id' => $setup['item']->id,
            'serial_number' => 'GSN-001',
            'status' => 'available',
        ]);
        $this->assertEquals(
            PurchaseOrderSupplierStatus::COMPLETED->value,
            $setup['pos']->fresh()->status
        );
    }

    public function test_marks_supplier_failed_when_gift2games_has_insufficient_balance(): void
    {
        $setup = $this->createOrderSetup('gift2games', '12345', 100.0);

        Http::fake([
            '*/check_balance' => Http::response([
                'status' => true,
                'data' => ['userBalance' => '0.00'],
            ], 200),
        ]);

        $this->dispatchJob($setup);

        $this->assertDatabaseCount('vouchers', 0);
        $this->assertEquals(
            PurchaseOrderSupplierStatus::FAILED->value,
            $setup['pos']->fresh()->status
        );
    }

    public function test_stores_no_vouchers_when_gift2games_order_creation_fails_mid_loop(): void
    {
        // Gift2GamesPlaceOrderService catches per-item exceptions and continues,
        // FIXME: so the job still marks the supplier COMPLETED even when all order calls fail.
        $setup = $this->createOrderSetup('gift2games', '12345');

        Http::fake([
            '*/check_balance' => Http::response([
                'status' => true,
                'data' => ['userBalance' => '500.00'],
            ], 200),
            '*/create_order' => Http::response([
                'status' => false,
                'error' => ['message' => 'Product not found'],
            ], 200),
        ]);

        $this->dispatchJob($setup);

        $this->assertDatabaseCount('vouchers', 0);
        $this->assertEquals(
            PurchaseOrderSupplierStatus::COMPLETED->value,
            $setup['pos']->fresh()->status
        );
    }

    // -------------------------------------------------------------------------
    // Giftery (legacy path)
    // -------------------------------------------------------------------------

    public function test_places_order_and_stores_vouchers_for_giftery(): void
    {
        $setup = $this->createOrderSetup('giftery-api', '9876', 30.0);

        cache()->put('giftery_refresh_token', 'fake-refresh', now()->addDays(7));

        Http::fake([
            '*/auth/refresh/*' => Http::response([
                'statusCode' => 0,
                'data' => ['accessToken' => 'fake-access-token', 'refreshToken' => 'fake-refresh-new'],
            ], 200),
            '*/accounts' => Http::response([
                'statusCode' => 0,
                'data' => [
                    ['available' => 1000, 'balance' => 1000, 'creditLimit' => 0, 'default' => true],
                ],
            ], 200),
            '*/products/items/*' => Http::response([
                'statusCode' => 0,
                'data' => ['inStock' => 10],
            ], 200),
            '*/operations/reserve' => Http::response([
                'statusCode' => 0,
                'data' => ['transactionUUID' => 'g-uuid-123'],
            ], 200),
            '*/operations/*/confirm' => Http::response([
                'statusCode' => 0,
                'data' => [
                    'vouchers' => [
                        ['serialNumber' => 'GSER-001', 'pin' => 'GPIN-001'],
                    ],
                ],
            ], 200),
        ]);

        $this->dispatchJob($setup);

        $this->assertDatabaseCount('vouchers', 1);
        $this->assertDatabaseHas('vouchers', [
            'purchase_order_id' => $setup['purchaseOrder']->id,
            'purchase_order_item_id' => $setup['item']->id,
            'serial_number' => 'GSER-001',
            'status' => 'available',
        ]);
        $this->assertEquals(
            PurchaseOrderSupplierStatus::COMPLETED->value,
            $setup['pos']->fresh()->status
        );
    }

    public function test_marks_supplier_failed_when_giftery_has_insufficient_balance(): void
    {
        $setup = $this->createOrderSetup('giftery-api', '9876', 500.0);

        cache()->put('giftery_refresh_token', 'fake-refresh', now()->addDays(7));

        Http::fake([
            '*/auth/refresh/*' => Http::response([
                'statusCode' => 0,
                'data' => ['accessToken' => 'fake-access-token', 'refreshToken' => 'fake-refresh-new'],
            ], 200),
            '*/accounts' => Http::response([
                'statusCode' => 0,
                'data' => [
                    ['available' => 0, 'balance' => 0, 'creditLimit' => 0, 'default' => true],
                ],
            ], 200),
        ]);

        $this->dispatchJob($setup);

        $this->assertDatabaseCount('vouchers', 0);
        $this->assertEquals(
            PurchaseOrderSupplierStatus::FAILED->value,
            $setup['pos']->fresh()->status
        );
    }

    // -------------------------------------------------------------------------
    // Tikkery (legacy path)
    // -------------------------------------------------------------------------

    public function test_places_order_and_stores_vouchers_when_tikkery_order_is_immediately_completed(): void
    {
        $setup = $this->createOrderSetup('tikkery', 'TIK-SKU-1');

        cache()->put('tikkery_access_token', 'fake-tikkery-token', now()->addHour());

        Http::fake([
            '*/balance*' => Http::response(['balance' => 500.0], 200),
            '*/products/stock*' => Http::response([
                'stock' => [['sku' => 'TIK-SKU-1', 'stock' => 10]],
            ], 200),
            '*/orders/create' => Http::response([
                'order' => ['number' => 'TIK-ORD-001', 'isCompleted' => true],
                'codes' => [
                    ['productSku' => 'TIK-SKU-1', 'redemptionCode' => 'TCODE-001', 'serial' => 'TSN-001', 'pin' => null],
                ],
            ], 200),
        ]);

        $this->dispatchJob($setup);

        $this->assertDatabaseCount('vouchers', 1);
        $this->assertDatabaseHas('vouchers', [
            'purchase_order_id' => $setup['purchaseOrder']->id,
            'serial_number' => 'TSN-001',
            'status' => 'available',
        ]);
        $this->assertEquals(
            PurchaseOrderSupplierStatus::COMPLETED->value,
            $setup['pos']->fresh()->status
        );
    }

    public function test_does_not_store_vouchers_when_tikkery_order_is_pending(): void
    {
        $setup = $this->createOrderSetup('tikkery', 'TIK-SKU-2');

        cache()->put('tikkery_access_token', 'fake-tikkery-token', now()->addHour());

        Http::fake([
            '*/balance*' => Http::response(['balance' => 500.0], 200),
            '*/products/stock*' => Http::response([
                'stock' => [['sku' => 'TIK-SKU-2', 'stock' => 10]],
            ], 200),
            '*/orders/create' => Http::response([
                'order' => ['number' => 'TIK-ORD-002', 'isCompleted' => false],
                'codes' => [],
            ], 200),
        ]);

        $this->dispatchJob($setup);

        $this->assertDatabaseCount('vouchers', 0);
        $this->assertEquals(
            PurchaseOrderSupplierStatus::PROCESSING->value,
            $setup['pos']->fresh()->status
        );
    }

    public function test_marks_supplier_failed_when_tikkery_api_errors(): void
    {
        $setup = $this->createOrderSetup('tikkery', 'TIK-SKU-3');

        cache()->put('tikkery_access_token', 'fake-tikkery-token', now()->addHour());

        Http::fake([
            '*/balance*' => Http::response(['error' => 'Internal Server Error'], 500),
        ]);

        $this->dispatchJob($setup);

        $this->assertDatabaseCount('vouchers', 0);
        $this->assertEquals(
            PurchaseOrderSupplierStatus::FAILED->value,
            $setup['pos']->fresh()->status
        );
    }
}
