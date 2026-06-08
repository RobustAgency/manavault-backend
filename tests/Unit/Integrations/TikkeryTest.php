<?php

namespace Tests\Unit\Integrations;

use Tests\TestCase;
use App\Models\Supplier;
use App\Integrations\Tikkery;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\Http;
use App\Models\PurchaseOrderSupplier;
use App\Enums\PurchaseOrderItemStatus;
use App\Enums\PurchaseOrderSupplierStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TikkeryTest extends TestCase
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

        $this->supplier = Supplier::factory()->create(['slug' => 'tikkery']);
        $this->product = DigitalProduct::factory()->forSupplier($this->supplier)->create(['sku' => 'SKU-001']);
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

        cache()->put('tikkery_access_token', 'fake-token', 3600);
    }

    private function createOrderResponse(string $number = 'TKR-ORDER-001'): array
    {
        return [
            '*/orders/create' => Http::response([
                'order' => ['number' => $number],
            ], 200),
        ];
    }

    private function getOrderResponse(string $number = 'TKR-ORDER-001', bool $isCompleted = false, array $codes = []): array
    {
        return [
            '*/orders/'.$number => Http::response([
                'order' => ['number' => $number, 'isCompleted' => $isCompleted],
                'codes' => $codes,
            ], 200),
        ];
    }

    private function sampleCodes(): array
    {
        return [
            ['redemptionCode' => 'CODE-ABC', 'serial' => 'SN-001', 'pin' => '9999'],
        ];
    }

    // -------------------------------------------------------------------------
    // placeOrder
    // -------------------------------------------------------------------------

    public function test_place_order_makes_api_call_and_sets_transaction_id(): void
    {
        Http::fake($this->createOrderResponse('TKR-ORDER-001'));

        app(Tikkery::class)->placeOrder($this->item);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/orders/create'));
        $this->assertEquals('TKR-ORDER-001', $this->item->fresh()->transaction_id);
    }

    public function test_place_order_sets_item_status_to_processing(): void
    {
        Http::fake($this->createOrderResponse());

        app(Tikkery::class)->placeOrder($this->item);

        $this->assertEquals(PurchaseOrderItemStatus::PROCESSING, $this->item->fresh()->status);
    }

    public function test_place_order_skips_when_transaction_id_already_exists(): void
    {
        Http::fake();

        $this->item->update(['transaction_id' => 'existing-order']);

        app(Tikkery::class)->placeOrder($this->item->fresh());

        Http::assertNothingSent();
        $this->assertEquals('existing-order', $this->item->fresh()->transaction_id);
    }

    public function test_place_order_does_not_store_vouchers(): void
    {
        Http::fake($this->createOrderResponse());

        app(Tikkery::class)->placeOrder($this->item);

        $this->assertDatabaseCount('vouchers', 0);
    }

    // -------------------------------------------------------------------------
    // updateOrder
    // -------------------------------------------------------------------------

    public function test_update_order_does_nothing_when_order_is_not_completed(): void
    {
        $this->item->update(['transaction_id' => 'TKR-ORDER-001', 'status' => PurchaseOrderItemStatus::PROCESSING->value]);

        Http::fake($this->getOrderResponse('TKR-ORDER-001', false, []));

        app(Tikkery::class)->updateOrder($this->item->fresh());

        $this->assertDatabaseCount('vouchers', 0);
        $this->assertEquals(PurchaseOrderItemStatus::PROCESSING, $this->item->fresh()->status);
    }

    public function test_update_order_stores_vouchers_and_marks_item_fulfilled(): void
    {
        $this->item->update(['transaction_id' => 'TKR-ORDER-001', 'status' => PurchaseOrderItemStatus::PROCESSING->value]);

        Http::fake($this->getOrderResponse('TKR-ORDER-001', true, $this->sampleCodes()));

        app(Tikkery::class)->updateOrder($this->item->fresh());

        $this->assertDatabaseCount('vouchers', 1);
        $this->assertDatabaseHas('vouchers', [
            'purchase_order_id' => $this->purchaseOrder->id,
            'purchase_order_item_id' => $this->item->id,
            'serial_number' => 'SN-001',
            'pin_code' => '9999',
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
            'transaction_id' => 'TKR-ALREADY-DONE',
            'status' => PurchaseOrderItemStatus::FULFILLED->value,
        ]);

        $this->item->update(['transaction_id' => 'TKR-ORDER-001', 'status' => PurchaseOrderItemStatus::PROCESSING->value]);

        Http::fake($this->getOrderResponse('TKR-ORDER-001', true, $this->sampleCodes()));

        app(Tikkery::class)->updateOrder($this->item->fresh());

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
            'transaction_id' => 'TKR-OTHER',
            'status' => PurchaseOrderItemStatus::PROCESSING->value,
        ]);

        $this->item->update(['transaction_id' => 'TKR-ORDER-001', 'status' => PurchaseOrderItemStatus::PROCESSING->value]);

        Http::fake($this->getOrderResponse('TKR-ORDER-001', true, $this->sampleCodes()));

        app(Tikkery::class)->updateOrder($this->item->fresh());

        $this->assertEquals(
            PurchaseOrderSupplierStatus::PROCESSING->value,
            $this->purchaseOrderSupplier->fresh()->status
        );
    }
}
