<?php

namespace Tests\Unit\Actions\PurchaseOrder;

use Tests\TestCase;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use App\Models\PurchaseOrderItem;
use App\Enums\PurchaseOrderStatus;
use Illuminate\Support\Facades\Http;
use App\Models\PurchaseOrderSupplier;
use App\Enums\PurchaseOrderItemStatus;
use App\Enums\PurchaseOrderSupplierStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Actions\PurchaseOrder\UpdatePurchaseOrderItemsAction;

class UpdatePurchaseOrderItemsActionTest extends TestCase
{
    use RefreshDatabase;

    private UpdatePurchaseOrderItemsAction $action;

    private Supplier $supplier;

    private DigitalProduct $product;

    private PurchaseOrder $purchaseOrder;

    private PurchaseOrderSupplier $purchaseOrderSupplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(UpdatePurchaseOrderItemsAction::class);
        $this->supplier = Supplier::factory()->create(['slug' => 'ez_cards']);
        $this->product = DigitalProduct::factory()->forSupplier($this->supplier)->create();
        $this->purchaseOrder = PurchaseOrder::factory()->create(['currency' => 'USD', 'status' => 'processing']);
        $this->purchaseOrderSupplier = PurchaseOrderSupplier::factory()->create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'supplier_id' => $this->supplier->id,
            'status' => PurchaseOrderSupplierStatus::PROCESSING->value,
        ]);
    }

    private function makeItem(string $transactionId, PurchaseOrderItemStatus $status = PurchaseOrderItemStatus::PROCESSING): PurchaseOrderItem
    {
        return PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'supplier_id' => $this->supplier->id,
            'digital_product_id' => $this->product->id,
            'transaction_id' => $transactionId,
            'status' => $status->value,
        ]);
    }

    private function fakeCodesCompleted(string $transactionId = '*'): void
    {
        Http::fake([
            "*/v2/orders/{$transactionId}/codes" => Http::response([
                'data' => [[
                    'codes' => [
                        ['status' => 'COMPLETED', 'redeemCode' => 'CODE-'.$transactionId, 'pinCode' => null, 'stockId' => null],
                    ],
                ]],
            ], 200),
        ]);
    }

    private function fakeCodesPending(string $transactionId = '*'): void
    {
        Http::fake([
            "*/v2/orders/{$transactionId}/codes" => Http::response([
                'data' => [[
                    'codes' => [
                        ['status' => 'PENDING', 'redeemCode' => 'CODE-'.$transactionId, 'pinCode' => null, 'stockId' => null],
                    ],
                ]],
            ], 200),
        ]);
    }

    // -------------------------------------------------------------------------
    // Supplier resolution
    // -------------------------------------------------------------------------

    public function test_skips_items_with_no_matching_supplier_integration(): void
    {
        Http::fake();

        $unknownSupplier = Supplier::factory()->create(['slug' => 'unintegrated_supplier']);
        $product = DigitalProduct::factory()->forSupplier($unknownSupplier)->create();
        $purchaseOrderSupplier = PurchaseOrderSupplier::factory()->create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'supplier_id' => $unknownSupplier->id,
            'status' => PurchaseOrderSupplierStatus::PROCESSING->value,
        ]);
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'supplier_id' => $unknownSupplier->id,
            'digital_product_id' => $product->id,
            'transaction_id' => 'TXN-001',
            'status' => PurchaseOrderItemStatus::PROCESSING->value,
        ]);

        $this->action->execute();

        Http::assertNothingSent();
        $this->assertEquals(
            PurchaseOrderSupplierStatus::PROCESSING->value,
            $purchaseOrderSupplier->fresh()->status
        );
    }

    public function test_ignores_items_with_non_processing_status(): void
    {
        Http::fake();

        $this->makeItem('1111', PurchaseOrderItemStatus::PENDING);
        $this->makeItem('2222', PurchaseOrderItemStatus::FULFILLED);

        $this->action->execute();

        Http::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // Cascade: supplier status
    // -------------------------------------------------------------------------

    public function test_does_not_cascade_when_item_remains_processing(): void
    {
        $this->makeItem('9999');
        $this->fakeCodesPending();

        $this->action->execute();

        $this->assertEquals(
            PurchaseOrderSupplierStatus::PROCESSING->value,
            $this->purchaseOrderSupplier->fresh()->status
        );
    }

    public function test_marks_supplier_completed_when_last_item_is_fulfilled(): void
    {
        $this->makeItem('9999');
        $this->fakeCodesCompleted();

        $this->action->execute();

        $this->assertEquals(
            PurchaseOrderSupplierStatus::COMPLETED->value,
            $this->purchaseOrderSupplier->fresh()->status
        );
    }

    public function test_does_not_mark_supplier_completed_when_other_items_still_processing(): void
    {
        $this->makeItem('9999');
        $this->makeItem('8888');

        Http::fake([
            '*/v2/orders/9999/codes' => Http::response([
                'data' => [[
                    'codes' => [
                        ['status' => 'COMPLETED', 'redeemCode' => 'CODE-9999', 'pinCode' => null, 'stockId' => null],
                    ],
                ]],
            ], 200),
            '*/v2/orders/8888/codes' => Http::response([
                'data' => [[
                    'codes' => [
                        ['status' => 'PENDING', 'redeemCode' => 'CODE-8888', 'pinCode' => null, 'stockId' => null],
                    ],
                ]],
            ], 200),
        ]);

        $this->action->execute();

        $this->assertEquals(
            PurchaseOrderSupplierStatus::PROCESSING->value,
            $this->purchaseOrderSupplier->fresh()->status
        );
    }

    // -------------------------------------------------------------------------
    // Cascade: purchase order status
    // -------------------------------------------------------------------------

    public function test_marks_purchase_order_completed_when_all_suppliers_are_completed(): void
    {
        $this->makeItem('9999');
        $this->fakeCodesCompleted();

        $this->action->execute();

        $this->assertEquals(
            PurchaseOrderStatus::COMPLETED->value,
            $this->purchaseOrder->fresh()->status
        );
    }

    public function test_does_not_mark_purchase_order_completed_when_supplier_is_still_processing(): void
    {
        $this->makeItem('9999');
        $this->fakeCodesPending();

        $this->action->execute();

        $this->assertNotEquals(
            PurchaseOrderStatus::COMPLETED->value,
            $this->purchaseOrder->fresh()->status
        );
    }
}
