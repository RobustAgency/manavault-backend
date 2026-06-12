<?php

namespace Tests\Unit\Integrations;

use Tests\TestCase;
use App\Models\Voucher;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use App\Integrations\Irewardify;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\Http;
use App\Models\PurchaseOrderSupplier;
use App\Enums\PurchaseOrderItemStatus;
use App\Enums\PurchaseOrderSupplierStatus;
use App\Services\Voucher\VoucherCipherService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class IrewardifyTest extends TestCase
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

        $this->supplier = Supplier::factory()->create(['slug' => 'irewardify']);
        $this->product = DigitalProduct::factory()->forSupplier($this->supplier)->create([
            'sku' => 'SKU-001',
            'metadata' => [
                'variant' => [
                    'item_id' => 'ITEM-123',
                    'product_id' => 'PROD-456',
                ],
            ],
        ]);
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

        cache()->put('irewardify_access_token', 'fake-token', 3600);
    }

    private function checkoutResponse(string $orderId = 'IRW-ORDER-001'): array
    {
        return [
            '*/checkout' => Http::response([
                'data' => ['orderId' => $orderId, 'externalOrderId' => 'order_item_id_'.$this->item->id],
            ], 200),
        ];
    }

    private function getOrderDeliveryResponse(string $orderId = 'IRW-ORDER-001', array $items = []): array
    {
        return [
            '*/order/delivery/'.$orderId => Http::response([
                'orderId' => $orderId,
                'data' => $items,
            ], 200),
        ];
    }

    private function sampleDeliveryItems(): array
    {
        return [
            [
                'no' => 1,
                'Brand' => 'Holland America',
                'Denom' => '$50.00',
                'Id' => '3581165-1',
                'Codes' => [
                    ['Label' => 'Code', 'Value' => '2370968749009790'],
                    ['Label' => 'PIN', 'Value' => '8755'],
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // placeOrder
    // -------------------------------------------------------------------------

    public function test_place_order_makes_api_call_and_sets_transaction_id(): void
    {
        Http::fake($this->checkoutResponse('IRW-ORDER-001'));

        app(Irewardify::class)->placeOrder($this->item);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/checkout'));
        $this->assertEquals('IRW-ORDER-001', $this->item->fresh()->transaction_id);
    }

    public function test_place_order_sets_item_status_to_processing(): void
    {
        Http::fake($this->checkoutResponse());

        app(Irewardify::class)->placeOrder($this->item);

        $this->assertEquals(PurchaseOrderItemStatus::PROCESSING, $this->item->fresh()->status);
    }

    public function test_place_order_skips_when_transaction_id_already_exists(): void
    {
        Http::fake();

        $this->item->update(['transaction_id' => 'existing-order']);

        app(Irewardify::class)->placeOrder($this->item->fresh());

        Http::assertNothingSent();
        $this->assertEquals('existing-order', $this->item->fresh()->transaction_id);
    }

    public function test_place_order_does_not_store_vouchers(): void
    {
        Http::fake($this->checkoutResponse());

        app(Irewardify::class)->placeOrder($this->item);

        $this->assertDatabaseCount('vouchers', 0);
    }

    public function test_place_order_throws_when_variant_metadata_is_missing(): void
    {
        $this->product->update(['metadata' => []]);

        Http::fake();

        $this->expectException(\RuntimeException::class);

        try {
            app(Irewardify::class)->placeOrder($this->item->fresh());
        } finally {
            Http::assertNothingSent();
        }
    }

    // -------------------------------------------------------------------------
    // updateOrder
    // -------------------------------------------------------------------------

    public function test_update_order_does_nothing_when_delivery_data_is_empty(): void
    {
        $this->item->update(['transaction_id' => 'IRW-ORDER-001', 'status' => PurchaseOrderItemStatus::PROCESSING->value]);

        Http::fake($this->getOrderDeliveryResponse('IRW-ORDER-001', []));

        app(Irewardify::class)->updateOrder($this->item->fresh());

        $this->assertDatabaseCount('vouchers', 0);
        $this->assertEquals(PurchaseOrderItemStatus::PROCESSING, $this->item->fresh()->status);
    }

    public function test_update_order_does_not_store_vouchers_when_delivery_count_does_not_match_quantity(): void
    {
        $this->item->update([
            'transaction_id' => 'IRW-ORDER-001',
            'status' => PurchaseOrderItemStatus::PROCESSING->value,
            'quantity' => 2,
        ]);

        Http::fake($this->getOrderDeliveryResponse('IRW-ORDER-001', $this->sampleDeliveryItems()));

        app(Irewardify::class)->updateOrder($this->item->fresh());

        $this->assertDatabaseCount('vouchers', 0);
        $this->assertEquals(PurchaseOrderItemStatus::PROCESSING, $this->item->fresh()->status);
    }

    public function test_update_order_stores_vouchers_and_marks_item_fulfilled(): void
    {
        $this->item->update(['transaction_id' => 'IRW-ORDER-001', 'status' => PurchaseOrderItemStatus::PROCESSING->value]);

        Http::fake($this->getOrderDeliveryResponse('IRW-ORDER-001', $this->sampleDeliveryItems()));

        app(Irewardify::class)->updateOrder($this->item->fresh());

        $this->assertDatabaseCount('vouchers', 1);
        $this->assertDatabaseHas('vouchers', [
            'purchase_order_id' => $this->purchaseOrder->id,
            'purchase_order_item_id' => $this->item->id,
            'pin_code' => '8755',
            'stock_id' => '3581165-1',
            'status' => 'available',
        ]);
        $this->assertEquals(PurchaseOrderItemStatus::FULFILLED, $this->item->fresh()->status);
    }

    public function test_update_order_stores_decryptable_voucher_code(): void
    {
        $this->item->update(['transaction_id' => 'IRW-ORDER-001', 'status' => PurchaseOrderItemStatus::PROCESSING->value]);

        Http::fake($this->getOrderDeliveryResponse('IRW-ORDER-001', $this->sampleDeliveryItems()));

        app(Irewardify::class)->updateOrder($this->item->fresh());

        $voucher = Voucher::query()->sole();
        $this->assertEquals('2370968749009790', app(VoucherCipherService::class)->decryptCode($voucher->code));
    }

    public function test_update_order_does_not_store_vouchers_when_delivery_item_has_no_code(): void
    {
        $this->item->update(['transaction_id' => 'IRW-ORDER-001', 'status' => PurchaseOrderItemStatus::PROCESSING->value]);

        Http::fake($this->getOrderDeliveryResponse('IRW-ORDER-001', [
            [
                'no' => 1,
                'Brand' => 'Holland America',
                'Denom' => '$50.00',
                'Id' => '3581165-1',
                'Codes' => [],
            ],
        ]));

        app(Irewardify::class)->updateOrder($this->item->fresh());

        $this->assertDatabaseCount('vouchers', 0);
        $this->assertEquals(PurchaseOrderItemStatus::PROCESSING, $this->item->fresh()->status);
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
            'transaction_id' => 'IRW-OTHER',
            'status' => PurchaseOrderItemStatus::PROCESSING->value,
        ]);

        $this->item->update(['transaction_id' => 'IRW-ORDER-001', 'status' => PurchaseOrderItemStatus::PROCESSING->value]);

        Http::fake($this->getOrderDeliveryResponse('IRW-ORDER-001', $this->sampleDeliveryItems()));

        app(Irewardify::class)->updateOrder($this->item->fresh());

        $this->assertEquals(
            PurchaseOrderSupplierStatus::PROCESSING->value,
            $this->purchaseOrderSupplier->fresh()->status
        );
    }
}
