<?php

namespace Tests\Unit\Integrations;

use Tests\TestCase;
use App\Models\Supplier;
use App\Integrations\Giftery;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\Http;
use App\Models\PurchaseOrderSupplier;
use App\Enums\PurchaseOrderItemStatus;
use App\Enums\PurchaseOrderSupplierStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GifteryTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    private DigitalProduct $product;

    private PurchaseOrder $purchaseOrder;

    private PurchaseOrderSupplier $purchaseOrderSupplier;

    private PurchaseOrderItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supplier = Supplier::factory()->create(['slug' => 'giftery-api']);
        $this->product = DigitalProduct::factory()->forSupplier($this->supplier)->create(['sku' => '12345']);
        $this->purchaseOrder = PurchaseOrder::factory()->create();
        $this->purchaseOrderSupplier = PurchaseOrderSupplier::factory()->create([
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

        cache()->put('giftery_refresh_token', 'fake-refresh', 3600);
    }

    private function authFake(): array
    {
        return [
            '*/auth/refresh/*' => Http::response([
                'statusCode' => 0,
                'data' => ['accessToken' => 'fake-token', 'refreshToken' => 'fake-refresh'],
            ], 200),
        ];
    }

    private function reserveResponse(string $uuid = 'TXN-UUID-001'): array
    {
        return [
            '*/operations/reserve' => Http::response([
                'statusCode' => 0,
                'data' => ['transactionUUID' => $uuid],
            ], 200),
        ];
    }

    private function confirmResponse(string $uuid = 'TXN-UUID-001'): array
    {
        return [
            '*/operations/'.$uuid.'/confirm' => Http::response([
                'statusCode' => 0,
                'data' => ['vouchers' => []],
            ], 200),
        ];
    }

    private function getOperationResponse(string $uuid = 'TXN-UUID-001', array $vouchers = []): array
    {
        return [
            '*/operations/'.$uuid => Http::response([
                'statusCode' => 0,
                'data' => ['vouchers' => $vouchers],
            ], 200),
        ];
    }

    private function sampleVouchers(): array
    {
        return [
            ['serialNumber' => 'SN-001', 'pin' => '1234', 'expiryDate' => '2027-01-01'],
        ];
    }

    // -------------------------------------------------------------------------
    // placeOrder
    // -------------------------------------------------------------------------

    public function test_place_order_makes_api_calls_and_sets_transaction_id(): void
    {
        Http::fake(array_merge(
            $this->authFake(),
            $this->reserveResponse(),
            $this->confirmResponse(),
        ));

        app(Giftery::class)->placeOrder($this->item);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/operations/reserve'));
        Http::assertSent(fn ($r) => str_contains($r->url(), '/operations/TXN-UUID-001/confirm'));
    }

    public function test_place_order_sets_transaction_id_on_item(): void
    {
        Http::fake(array_merge(
            $this->authFake(),
            $this->reserveResponse('TXN-UUID-001'),
            $this->confirmResponse('TXN-UUID-001'),
        ));

        app(Giftery::class)->placeOrder($this->item);

        $this->assertEquals('TXN-UUID-001', $this->item->fresh()->transaction_id);
    }

    public function test_place_order_sets_item_status_to_processing(): void
    {
        Http::fake(array_merge(
            $this->authFake(),
            $this->reserveResponse(),
            $this->confirmResponse(),
        ));

        app(Giftery::class)->placeOrder($this->item);

        $this->assertEquals(PurchaseOrderItemStatus::PROCESSING, $this->item->fresh()->status);
    }

    public function test_place_order_does_not_store_vouchers(): void
    {
        Http::fake(array_merge(
            $this->authFake(),
            $this->reserveResponse(),
            $this->confirmResponse(),
        ));

        app(Giftery::class)->placeOrder($this->item);

        $this->assertDatabaseCount('vouchers', 0);
    }

    public function test_place_order_skips_when_transaction_id_already_exists(): void
    {
        Http::fake();

        $this->item->update(['transaction_id' => 'existing-uuid']);

        app(Giftery::class)->placeOrder($this->item->fresh());

        Http::assertNothingSent();
        $this->assertEquals('existing-uuid', $this->item->fresh()->transaction_id);
    }

    // -------------------------------------------------------------------------
    // updateOrder
    // -------------------------------------------------------------------------

    public function test_update_order_does_nothing_when_get_operation_returns_no_vouchers(): void
    {
        $this->item->update(['transaction_id' => 'TXN-UUID-001', 'status' => PurchaseOrderItemStatus::PROCESSING->value]);

        Http::fake(array_merge(
            $this->authFake(),
            $this->getOperationResponse('TXN-UUID-001', []),
        ));

        app(Giftery::class)->updateOrder($this->item->fresh());

        $this->assertDatabaseCount('vouchers', 0);
        $this->assertEquals(PurchaseOrderItemStatus::PROCESSING, $this->item->fresh()->status);
    }

    public function test_update_order_stores_vouchers_and_marks_item_fulfilled(): void
    {
        $this->item->update(['transaction_id' => 'TXN-UUID-001', 'status' => PurchaseOrderItemStatus::PROCESSING->value]);

        Http::fake(array_merge(
            $this->authFake(),
            $this->getOperationResponse('TXN-UUID-001', $this->sampleVouchers()),
        ));

        app(Giftery::class)->updateOrder($this->item->fresh());

        $this->assertDatabaseCount('vouchers', 1);
        $this->assertDatabaseHas('vouchers', [
            'purchase_order_id' => $this->purchaseOrder->id,
            'purchase_order_item_id' => $this->item->id,
            'serial_number' => 'SN-001',
            'status' => 'available',
        ]);
        $this->assertEquals(PurchaseOrderItemStatus::FULFILLED, $this->item->fresh()->status);
    }

    public function test_update_order_marks_supplier_completed_when_all_items_fulfilled(): void
    {
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'supplier_id' => $this->supplier->id,
            'digital_product_id' => $this->product->id,
            'quantity' => 1,
            'unit_cost' => 5.00,
            'subtotal' => 5.00,
            'transaction_id' => 'TXN-ALREADY-DONE',
            'status' => PurchaseOrderItemStatus::FULFILLED->value,
        ]);

        $this->item->update(['transaction_id' => 'TXN-UUID-001', 'status' => PurchaseOrderItemStatus::PROCESSING->value]);

        Http::fake(array_merge(
            $this->authFake(),
            $this->getOperationResponse('TXN-UUID-001', $this->sampleVouchers()),
        ));

        app(Giftery::class)->updateOrder($this->item->fresh());

        $this->assertEquals(
            PurchaseOrderSupplierStatus::COMPLETED->value,
            $this->purchaseOrderSupplier->fresh()->status
        );
    }

    public function test_update_order_does_not_mark_supplier_completed_when_other_items_still_pending(): void
    {
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'supplier_id' => $this->supplier->id,
            'digital_product_id' => $this->product->id,
            'quantity' => 1,
            'unit_cost' => 5.00,
            'subtotal' => 5.00,
            'transaction_id' => 'TXN-OTHER',
            'status' => PurchaseOrderItemStatus::PROCESSING->value,
        ]);

        $this->item->update(['transaction_id' => 'TXN-UUID-001', 'status' => PurchaseOrderItemStatus::PROCESSING->value]);

        Http::fake(array_merge(
            $this->authFake(),
            $this->getOperationResponse('TXN-UUID-001', $this->sampleVouchers()),
        ));

        app(Giftery::class)->updateOrder($this->item->fresh());

        $this->assertEquals(
            PurchaseOrderSupplierStatus::PROCESSING->value,
            $this->purchaseOrderSupplier->fresh()->status
        );
    }
}
