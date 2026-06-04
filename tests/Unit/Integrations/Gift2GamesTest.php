<?php

namespace Tests\Unit\Integrations;

use Tests\TestCase;
use App\Models\Voucher;
use App\Models\Supplier;
use App\Integrations\Gift2Games;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\Http;
use App\Enums\PurchaseOrderItemStatus;
use App\Models\PurchaseOrderSupplier;
use App\Enums\PurchaseOrderSupplierStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

class Gift2GamesTest extends TestCase
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

        $this->supplier = Supplier::factory()->create(['slug' => 'gift2games']);
        $this->product = DigitalProduct::factory()->forSupplier($this->supplier)->create(['sku' => '12345']);
        $this->purchaseOrder = PurchaseOrder::factory()->create([
            'order_number' => 'PO-TEST-001',
            'currency' => 'USD',
        ]);
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
    }

    private function makeIntegration(string $slug = 'gift2games'): Gift2Games
    {
        return app()->makeWith(Gift2Games::class, ['supplierSlug' => $slug]);
    }

    public function test_place_order_creates_voucher_and_marks_item_fulfilled(): void
    {
        Http::fake([
            '*/check_balance' => Http::response(['status' => true, 'data' => ['userBalance' => '500.00']], 200),
            '*/create_order' => Http::response(['status' => true, 'data' => ['serialCode' => 'CODE-001', 'serialNumber' => 'SN-001']], 200),
        ]);

        $this->makeIntegration()->placeOrder($this->item);

        $this->assertDatabaseCount('vouchers', 1);
        $this->assertDatabaseHas('vouchers', [
            'purchase_order_id' => $this->purchaseOrder->id,
            'purchase_order_item_id' => $this->item->id,
            'serial_number' => 'SN-001',
            'status' => 'available',
        ]);
        $this->assertEquals(PurchaseOrderItemStatus::FULFILLED, $this->item->fresh()->status);
    }

    public function test_place_order_makes_one_api_call_per_unit_for_quantity_greater_than_one(): void
    {
        $this->item->update(['quantity' => 3, 'subtotal' => 30.00]);

        Http::fake([
            '*/check_balance' => Http::response(['status' => true, 'data' => ['userBalance' => '500.00']], 200),
            '*/create_order' => Http::sequence()
                ->push(['status' => true, 'data' => ['serialCode' => 'CODE-001', 'serialNumber' => 'SN-001']], 200)
                ->push(['status' => true, 'data' => ['serialCode' => 'CODE-002', 'serialNumber' => 'SN-002']], 200)
                ->push(['status' => true, 'data' => ['serialCode' => 'CODE-003', 'serialNumber' => 'SN-003']], 200),
        ]);

        $this->makeIntegration()->placeOrder($this->item->fresh());

        $this->assertDatabaseCount('vouchers', 3);
        $this->assertEquals(PurchaseOrderItemStatus::FULFILLED, $this->item->fresh()->status);
    }

    public function test_place_order_marks_supplier_completed_when_all_items_fulfilled(): void
    {
        Http::fake([
            '*/check_balance' => Http::response(['status' => true, 'data' => ['userBalance' => '500.00']], 200),
            '*/create_order' => Http::response(['status' => true, 'data' => ['serialCode' => 'CODE-001', 'serialNumber' => 'SN-001']], 200),
        ]);

        $this->makeIntegration()->placeOrder($this->item);

        $this->assertEquals(
            PurchaseOrderSupplierStatus::COMPLETED->value,
            $this->purchaseOrderSupplier->fresh()->status,
        );
    }

    public function test_place_order_does_not_mark_supplier_completed_when_other_items_still_pending(): void
    {
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'supplier_id' => $this->supplier->id,
            'digital_product_id' => $this->product->id,
            'quantity' => 1,
            'unit_cost' => 10.00,
            'subtotal' => 10.00,
            'transaction_id' => null,
            'status' => PurchaseOrderItemStatus::PENDING->value,
        ]);

        Http::fake([
            '*/check_balance' => Http::response(['status' => true, 'data' => ['userBalance' => '500.00']], 200),
            '*/create_order' => Http::response(['status' => true, 'data' => ['serialCode' => 'CODE-001', 'serialNumber' => 'SN-001']], 200),
        ]);

        $this->makeIntegration()->placeOrder($this->item);

        $this->assertEquals(
            PurchaseOrderSupplierStatus::PROCESSING->value,
            $this->purchaseOrderSupplier->fresh()->status,
        );
    }

    public function test_place_order_marks_supplier_failed_when_balance_is_insufficient(): void
    {
        Http::fake([
            '*/check_balance' => Http::response(['status' => true, 'data' => ['userBalance' => '0.00']], 200),
        ]);

        $this->expectException(\Exception::class);

        $this->makeIntegration()->placeOrder($this->item);

        $this->assertDatabaseCount('vouchers', 0);
        $this->assertEquals(
            PurchaseOrderSupplierStatus::FAILED->value,
            $this->purchaseOrderSupplier->fresh()->status,
        );
    }

    public function test_place_order_skips_already_created_vouchers_on_retry(): void
    {
        $this->item->update(['quantity' => 2, 'subtotal' => 20.00]);

        Voucher::create([
            'purchase_order_id' => $this->purchaseOrder->id,
            'purchase_order_item_id' => $this->item->id,
            'code' => 'existing-encrypted-code',
            'serial_number' => 'SN-EXISTING',
            'status' => 'available',
        ]);

        Http::fake([
            '*/check_balance' => Http::response(['status' => true, 'data' => ['userBalance' => '500.00']], 200),
            '*/create_order' => Http::response(['status' => true, 'data' => ['serialCode' => 'CODE-NEW', 'serialNumber' => 'SN-NEW']], 200),
        ]);

        $this->makeIntegration()->placeOrder($this->item->fresh());

        $this->assertDatabaseCount('vouchers', 2);
        Http::assertSentCount(2); // 1 balance check + 1 create_order (not 2)
    }

    public function test_update_order_is_a_noop(): void
    {
        Http::fake();

        $this->makeIntegration()->updateOrder($this->item);

        Http::assertNothingSent();
        $this->assertDatabaseCount('vouchers', 0);
        $this->assertEquals(PurchaseOrderItemStatus::PENDING, $this->item->fresh()->status);
    }
}
