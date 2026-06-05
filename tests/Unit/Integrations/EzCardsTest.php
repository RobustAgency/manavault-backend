<?php

namespace Tests\Unit\Integrations;

use Tests\TestCase;
use App\Models\Supplier;
use App\Integrations\EzCards;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\Http;
use App\Models\PurchaseOrderSupplier;
use App\Enums\PurchaseOrderItemStatus;
use App\Enums\PurchaseOrderSupplierStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EzCardsTest extends TestCase
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

        $this->supplier = Supplier::factory()->create(['slug' => 'ez_cards']);
        $this->product = DigitalProduct::factory()->forSupplier($this->supplier)->create(['sku' => 'EZC-001']);
        $this->purchaseOrder = PurchaseOrder::factory()->create(['currency' => 'USD']);
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
            'quantity' => 2,
            'unit_cost' => 10.00,
            'subtotal' => 20.00,
            'transaction_id' => null,
            'status' => PurchaseOrderItemStatus::PENDING->value,
        ]);
    }

    public function test_place_order_stores_transaction_id_and_sets_processing_status(): void
    {
        Http::fake([
            '*/v2/orders' => Http::response([
                'data' => [
                    'transactionId' => '9999',
                    'status' => 'PROCESSING',
                ],
            ], 200),
        ]);

        app(EzCards::class)->placeOrder($this->item);

        $fresh = $this->item->fresh();
        $this->assertEquals('9999', $fresh->transaction_id);
        $this->assertEquals(PurchaseOrderItemStatus::PROCESSING, $fresh->status);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/v2/orders')
                && $request->method() === 'POST'
                && $request['clientOrderNumber'] === 'order_item_id_'.$this->item->id;
        });
    }

    public function test_place_order_skips_when_transaction_id_already_exists(): void
    {
        Http::fake();

        $this->item->update(['transaction_id' => 'existing-123']);

        app(EzCards::class)->placeOrder($this->item->fresh());

        Http::assertNothingSent();
        $this->assertEquals('existing-123', $this->item->fresh()->transaction_id);
    }

    public function test_update_order_does_nothing_when_codes_not_yet_completed(): void
    {
        $this->item->update(['transaction_id' => '9999', 'status' => PurchaseOrderItemStatus::PROCESSING->value]);

        Http::fake([
            '*/v2/orders/*/codes' => Http::response([
                'data' => [
                    [
                        'codes' => [
                            ['status' => 'PENDING', 'redeemCode' => 'C1', 'pinCode' => null, 'stockId' => null],
                        ],
                    ],
                ],
            ], 200),
        ]);

        app(EzCards::class)->updateOrder($this->item->fresh());

        $this->assertDatabaseCount('vouchers', 0);
        $this->assertEquals(PurchaseOrderItemStatus::PROCESSING, $this->item->fresh()->status);
    }

    public function test_update_order_stores_vouchers_and_marks_item_fulfilled_when_all_completed(): void
    {
        $this->item->update(['transaction_id' => '9999', 'status' => PurchaseOrderItemStatus::PROCESSING->value]);

        Http::fake([
            '*/v2/orders/*/codes' => Http::response([
                'data' => [
                    [
                        'codes' => [
                            ['status' => 'COMPLETED', 'redeemCode' => 'CODE-ABC', 'pinCode' => '1234', 'stockId' => 'ST1'],
                            ['status' => 'COMPLETED', 'redeemCode' => 'CODE-XYZ', 'pinCode' => '5678', 'stockId' => 'ST2'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        app(EzCards::class)->updateOrder($this->item->fresh());

        $this->assertDatabaseCount('vouchers', 2);
        $this->assertDatabaseHas('vouchers', [
            'purchase_order_id' => $this->purchaseOrder->id,
            'purchase_order_item_id' => $this->item->id,
            'pin_code' => '1234',
            'stock_id' => 'ST1',
            'status' => 'available',
        ]);
        $this->assertEquals(PurchaseOrderItemStatus::FULFILLED, $this->item->fresh()->status);
    }

    public function test_update_order_marks_supplier_completed_when_all_items_fulfilled(): void
    {
        $item2 = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'supplier_id' => $this->supplier->id,
            'digital_product_id' => $this->product->id,
            'quantity' => 1,
            'unit_cost' => 5.00,
            'subtotal' => 5.00,
            'transaction_id' => '8888',
            'status' => PurchaseOrderItemStatus::FULFILLED->value,
        ]);

        $this->item->update(['transaction_id' => '9999', 'status' => PurchaseOrderItemStatus::PROCESSING->value]);

        Http::fake([
            '*/v2/orders/*/codes' => Http::response([
                'data' => [
                    [
                        'codes' => [
                            ['status' => 'COMPLETED', 'redeemCode' => 'FINAL-CODE', 'pinCode' => null, 'stockId' => null],
                        ],
                    ],
                ],
            ], 200),
        ]);

        app(EzCards::class)->updateOrder($this->item->fresh());

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
            'transaction_id' => '7777',
            'status' => PurchaseOrderItemStatus::PROCESSING->value,
        ]);

        $this->item->update(['transaction_id' => '9999', 'status' => PurchaseOrderItemStatus::PROCESSING->value]);

        Http::fake([
            '*/v2/orders/*/codes' => Http::response([
                'data' => [
                    [
                        'codes' => [
                            ['status' => 'COMPLETED', 'redeemCode' => 'PARTIAL-CODE', 'pinCode' => null, 'stockId' => null],
                        ],
                    ],
                ],
            ], 200),
        ]);

        app(EzCards::class)->updateOrder($this->item->fresh());

        $this->assertEquals(
            PurchaseOrderSupplierStatus::PROCESSING->value,
            $this->purchaseOrderSupplier->fresh()->status
        );
    }
}
