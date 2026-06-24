<?php

namespace Tests\Unit\Actions\PurchaseOrder;

use Tests\TestCase;
use App\Models\Voucher;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use App\Enums\VoucherCodeStatus;
use App\Models\PurchaseOrderItem;
use App\Enums\PurchaseOrderStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use App\Enums\PurchaseOrderItemStatus;
use App\Jobs\PlaceExternalPurchaseOrderJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Actions\PurchaseOrder\ExtractAvailableVouchersToNewPurchaseOrder;

class ExtractAvailableVouchersToNewPurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private ExtractAvailableVouchersToNewPurchaseOrder $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(ExtractAvailableVouchersToNewPurchaseOrder::class);
    }

    /**
     * Create a line item (with its supplier + digital product) on the given purchase order.
     *
     * @return array{0: PurchaseOrder, 1: PurchaseOrderItem, 2: Supplier, 3: DigitalProduct}
     */
    private function makeSourceItem(?PurchaseOrder $purchaseOrder = null): array
    {
        $supplier = Supplier::factory()->create();
        $digitalProduct = DigitalProduct::factory()->create();
        $purchaseOrder ??= PurchaseOrder::factory()->create([
            'sale_order_id' => null,
            'currency' => 'usd',
        ]);

        $item = PurchaseOrderItem::factory()
            ->forPurchaseOrder($purchaseOrder)
            ->forDigitalProduct($digitalProduct)
            ->create(['supplier_id' => $supplier->id]);

        return [$purchaseOrder, $item, $supplier, $digitalProduct];
    }

    /**
     * @return Collection<int, Voucher>
     */
    private function makeVouchers(PurchaseOrder $purchaseOrder, PurchaseOrderItem $item, int $count, string $status): Collection
    {
        return Voucher::factory()->count($count)->create([
            'purchase_order_id' => $purchaseOrder->id,
            'purchase_order_item_id' => $item->id,
            'status' => $status,
        ]);
    }

    public function test_moves_only_available_vouchers_and_leaves_others_on_source(): void
    {
        [$source, $item] = $this->makeSourceItem();
        $available = $this->makeVouchers($source, $item, 3, VoucherCodeStatus::AVAILABLE->value);
        $allocated = $this->makeVouchers($source, $item, 2, VoucherCodeStatus::ALLOCATED->value);

        $summary = $this->action->execute($source->id);

        $this->assertEquals(3, $summary['vouchers_moved']);
        $newPoId = $summary['new_purchase_order_id'];
        $this->assertNotNull($newPoId);
        $this->assertNotEquals($source->id, $newPoId);

        // Exactly two purchase orders exist now: the source and the new one.
        $this->assertEquals(2, PurchaseOrder::count());

        // The new purchase order has a matching line item for the moved vouchers.
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $newPoId,
            'digital_product_id' => $item->digital_product_id,
            'quantity' => 3,
        ]);

        foreach ($available as $voucher) {
            $this->assertDatabaseHas('vouchers', [
                'id' => $voucher->id,
                'purchase_order_id' => $newPoId,
                'status' => VoucherCodeStatus::AVAILABLE->value,
            ]);
        }

        foreach ($allocated as $voucher) {
            $this->assertDatabaseHas('vouchers', [
                'id' => $voucher->id,
                'purchase_order_id' => $source->id,
                'status' => VoucherCodeStatus::ALLOCATED->value,
            ]);
        }
    }

    public function test_new_purchase_order_is_general_stock_with_matching_item(): void
    {
        [$source, $item, , $digitalProduct] = $this->makeSourceItem();
        $vouchers = $this->makeVouchers($source, $item, 2, VoucherCodeStatus::AVAILABLE->value);

        $summary = $this->action->execute($source->id);
        $newPoId = $summary['new_purchase_order_id'];

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $newPoId,
            'sale_order_id' => null,
            'status' => PurchaseOrderStatus::COMPLETED->value,
            'currency' => 'usd',
        ]);

        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $newPoId,
            'digital_product_id' => $digitalProduct->id,
            'quantity' => 2,
            'status' => PurchaseOrderItemStatus::FULFILLED->value,
        ]);

        // Each moved voucher is repointed to BOTH the new PO and the new item.
        $newItem = PurchaseOrderItem::where('purchase_order_id', $newPoId)->firstOrFail();
        foreach ($vouchers as $voucher) {
            $this->assertDatabaseHas('vouchers', [
                'id' => $voucher->id,
                'purchase_order_id' => $newPoId,
                'purchase_order_item_id' => $newItem->id,
            ]);
        }
    }

    public function test_creates_one_item_per_digital_product(): void
    {
        $source = PurchaseOrder::factory()->create(['sale_order_id' => null, 'currency' => 'usd']);
        [, $itemA] = $this->makeSourceItem($source);
        [, $itemB] = $this->makeSourceItem($source);
        $this->makeVouchers($source, $itemA, 2, VoucherCodeStatus::AVAILABLE->value);
        $this->makeVouchers($source, $itemB, 3, VoucherCodeStatus::AVAILABLE->value);

        $summary = $this->action->execute($source->id);

        $this->assertEquals(5, $summary['vouchers_moved']);
        $this->assertEquals(2, $summary['items_created']);
        $this->assertEquals(
            2,
            PurchaseOrderItem::where('purchase_order_id', $summary['new_purchase_order_id'])->count(),
        );
    }

    public function test_dry_run_persists_nothing_but_reports_counts(): void
    {
        [$source, $item] = $this->makeSourceItem();
        $this->makeVouchers($source, $item, 2, VoucherCodeStatus::AVAILABLE->value);

        $purchaseOrdersBefore = PurchaseOrder::count();

        $summary = $this->action->execute($source->id, dryRun: true);

        $this->assertEquals(2, $summary['vouchers_moved']);
        $this->assertNull($summary['new_purchase_order_id']);

        // No new purchase order persisted, vouchers untouched on the source order.
        $this->assertEquals($purchaseOrdersBefore, PurchaseOrder::count());
        $this->assertEquals(
            2,
            Voucher::where('purchase_order_id', $source->id)
                ->where('status', VoucherCodeStatus::AVAILABLE->value)
                ->count(),
        );
    }

    public function test_no_available_vouchers_creates_no_purchase_order(): void
    {
        [$source, $item] = $this->makeSourceItem();
        $this->makeVouchers($source, $item, 2, VoucherCodeStatus::ALLOCATED->value);

        $purchaseOrdersBefore = PurchaseOrder::count();

        $summary = $this->action->execute($source->id);

        $this->assertEquals(0, $summary['vouchers_moved']);
        $this->assertNull($summary['new_purchase_order_id']);
        $this->assertEquals($purchaseOrdersBefore, PurchaseOrder::count());
    }

    public function test_does_not_dispatch_external_purchase_order_job(): void
    {
        Queue::fake();

        [$source, $item] = $this->makeSourceItem();
        $this->makeVouchers($source, $item, 2, VoucherCodeStatus::AVAILABLE->value);

        $this->action->execute($source->id);

        Queue::assertNotPushed(PlaceExternalPurchaseOrderJob::class);
    }

    public function test_skips_vouchers_without_a_purchase_order_item(): void
    {
        [$source, $item] = $this->makeSourceItem();
        $this->makeVouchers($source, $item, 1, VoucherCodeStatus::AVAILABLE->value);

        Voucher::factory()->create([
            'purchase_order_id' => $source->id,
            'purchase_order_item_id' => null,
            'status' => VoucherCodeStatus::AVAILABLE->value,
        ]);

        $summary = $this->action->execute($source->id);

        $this->assertEquals(1, $summary['vouchers_moved']);
        $this->assertEquals(1, $summary['skipped']);
    }

    public function test_throws_when_source_purchase_order_does_not_exist(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->action->execute(999999);
    }
}
