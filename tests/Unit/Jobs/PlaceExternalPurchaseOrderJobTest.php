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
    // Gift2Games (integration path — placeOrder only, updateOrder is separate)
    // -------------------------------------------------------------------------

    public function test_gift2games_place_order_creates_batch_records_and_sets_item_to_processing(): void
    {
        Http::fake();

        $setup = $this->createOrderSetup('gift2games', '12345');

        $this->dispatchJob($setup);

        $this->assertDatabaseCount('vouchers', 0);
        $this->assertDatabaseCount('gift2games_orders', 1);
        $this->assertDatabaseHas('gift2games_orders', [
            'batch_number' => 'batch_'.$setup['item']->id,
        ]);
        $this->assertEquals(PurchaseOrderItemStatus::PROCESSING, $setup['item']->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_gift2games_eur_place_order_creates_batch_records_and_sets_item_to_processing(): void
    {
        Http::fake();

        $setup = $this->createOrderSetup('gift-2-games-eur', '12345');

        $this->dispatchJob($setup);

        $this->assertDatabaseCount('vouchers', 0);
        $this->assertDatabaseCount('gift2games_orders', 1);
        $this->assertEquals(PurchaseOrderItemStatus::PROCESSING, $setup['item']->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_gift2games_gbp_place_order_creates_batch_records_and_sets_item_to_processing(): void
    {
        Http::fake();

        $setup = $this->createOrderSetup('gift-2-games-gbp', '12345');

        $this->dispatchJob($setup);

        $this->assertDatabaseCount('vouchers', 0);
        $this->assertDatabaseCount('gift2games_orders', 1);
        $this->assertEquals(PurchaseOrderItemStatus::PROCESSING, $setup['item']->fresh()->status);
        Http::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // Giftery (new-style integration — placeOrder only, updateOrder is separate)
    // -------------------------------------------------------------------------

    public function test_places_order_for_giftery_via_integration(): void
    {
        $setup = $this->createOrderSetup('giftery-api', '9876');

        cache()->put('giftery_refresh_token', 'fake-refresh', now()->addDays(7));

        Http::fake([
            '*/auth/refresh/*' => Http::response([
                'statusCode' => 0,
                'data' => ['accessToken' => 'fake-token', 'refreshToken' => 'fake-refresh'],
            ], 200),
            '*/operations/reserve' => Http::response([
                'statusCode' => 0,
                'data' => ['transactionUUID' => 'g-uuid-123'],
            ], 200),
            '*/operations/*/confirm' => Http::response([
                'statusCode' => 0,
                'data' => ['vouchers' => []],
            ], 200),
        ]);

        $this->dispatchJob($setup);

        $fresh = $setup['item']->fresh();
        $this->assertEquals('g-uuid-123', $fresh->transaction_id);
        $this->assertEquals(PurchaseOrderItemStatus::PROCESSING, $fresh->status);
        $this->assertDatabaseCount('vouchers', 0);
    }

    public function test_giftery_continues_processing_remaining_items_when_one_fails(): void
    {
        $setup = $this->createOrderSetup('giftery-api', '9876');

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

        cache()->put('giftery_refresh_token', 'fake-refresh', now()->addDays(7));

        Http::fake([
            '*/auth/refresh/*' => Http::response([
                'statusCode' => 0,
                'data' => ['accessToken' => 'fake-token', 'refreshToken' => 'fake-refresh'],
            ], 200),
            '*/operations/reserve' => Http::sequence()
                ->push(['statusCode' => -1, 'message' => 'Item unavailable'], 200)
                ->push(['statusCode' => 0, 'data' => ['transactionUUID' => 'g-uuid-456']], 200),
            '*/operations/*/confirm' => Http::response([
                'statusCode' => 0,
                'data' => ['vouchers' => []],
            ], 200),
        ]);

        $this->dispatchJob($setup, [$item2]);

        $this->assertNull($setup['item']->fresh()->transaction_id);
        $this->assertEquals('g-uuid-456', $item2->fresh()->transaction_id);
    }

    // -------------------------------------------------------------------------
    // Tikkery (integration path — placeOrder only, updateOrder is separate)
    // -------------------------------------------------------------------------

    public function test_places_order_for_tikkery_via_integration(): void
    {
        $setup = $this->createOrderSetup('tikkery', 'TIK-SKU-1');

        cache()->put('tikkery_access_token', 'fake-tikkery-token', now()->addHour());

        Http::fake([
            '*/orders/create' => Http::response([
                'order' => ['number' => 'TIK-ORD-001'],
            ], 200),
        ]);

        $this->dispatchJob($setup);

        $fresh = $setup['item']->fresh();
        $this->assertEquals('TIK-ORD-001', $fresh->transaction_id);
        $this->assertEquals(PurchaseOrderItemStatus::PROCESSING, $fresh->status);
        $this->assertDatabaseCount('vouchers', 0);
    }

    public function test_tikkery_continues_processing_remaining_items_when_one_fails(): void
    {
        $setup = $this->createOrderSetup('tikkery', 'TIK-SKU-1');

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

        cache()->put('tikkery_access_token', 'fake-tikkery-token', now()->addHour());

        Http::fake([
            '*/orders/create' => Http::sequence()
                ->push(['error' => 'Bad Request'], 400)
                ->push(['order' => ['number' => 'TIK-ORD-002']], 200),
        ]);

        $this->dispatchJob($setup, [$item2]);

        $this->assertNull($setup['item']->fresh()->transaction_id);
        $this->assertEquals('TIK-ORD-002', $item2->fresh()->transaction_id);
    }
}
