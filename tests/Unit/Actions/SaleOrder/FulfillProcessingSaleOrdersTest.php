<?php

namespace Tests\Unit\Actions\SaleOrder;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Voucher;
use App\Models\Supplier;
use App\Models\SaleOrder;
use App\Models\PurchaseOrder;
use App\Models\SaleOrderItem;
use App\Models\DigitalProduct;
use App\Enums\SaleOrder\Status;
use App\Enums\VoucherCodeStatus;
use App\Models\PurchaseOrderItem;
use App\Events\SaleOrderCompleted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use App\Jobs\PlaceExternalPurchaseOrderJob;
use App\Models\SaleOrderItemDigitalProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Actions\SaleOrder\FulfillProcessingSaleOrders;

class FulfillProcessingSaleOrdersTest extends TestCase
{
    use RefreshDatabase;

    private FulfillProcessingSaleOrders $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(FulfillProcessingSaleOrders::class);
    }

    public function test_legacy_order_with_general_stock_is_completed_and_backfilled(): void
    {
        Event::fake([SaleOrderCompleted::class]);

        $supplier = Supplier::factory()->create(['type' => 'internal']);
        [$product, $digitalProduct] = $this->makeProductWithDigitalProduct($supplier);
        $this->addGeneralStock($digitalProduct, 3);

        // Legacy item: created under old code with no persisted digital_product_id.
        $saleOrder = $this->makeProcessingOrder($product, quantity: 3, digitalProductId: null);

        $this->action->execute();

        $saleOrder->refresh();
        $item = $saleOrder->items->first();

        $this->assertEquals(Status::COMPLETED->value, $saleOrder->status);
        $this->assertEquals($digitalProduct->id, $item->digital_product_id, 'digital_product_id should be backfilled');
        $this->assertEquals(3, $item->digitalProducts()->count(), 'all units allocated from general stock');
        Event::assertDispatched(SaleOrderCompleted::class);
    }

    public function test_order_with_no_stock_creates_linked_purchase_order(): void
    {
        Queue::fake();
        Event::fake([SaleOrderCompleted::class]);

        $supplier = Supplier::factory()->create(['slug' => 'ez_cards', 'type' => 'external']);
        [$product, $digitalProduct] = $this->makeProductWithDigitalProduct($supplier);

        $saleOrder = $this->makeProcessingOrder($product, quantity: 2, digitalProductId: null);

        $this->action->execute();

        $saleOrder->refresh();
        $linkedPo = PurchaseOrder::where('sale_order_id', $saleOrder->id)->first();

        $this->assertEquals(Status::PROCESSING->value, $saleOrder->status);
        $this->assertNotNull($linkedPo, 'a purchase order linked to the sale order should be created');
        $this->assertEquals($digitalProduct->id, $linkedPo->items->first()->digital_product_id);
        $this->assertEquals(2, $linkedPo->items->first()->quantity);
        Queue::assertPushed(PlaceExternalPurchaseOrderJob::class, 1);
        Event::assertNotDispatched(SaleOrderCompleted::class);
    }

    public function test_partial_general_stock_allocates_and_creates_po_for_remainder(): void
    {
        Queue::fake();

        $supplier = Supplier::factory()->create(['type' => 'internal']);
        [$product, $digitalProduct] = $this->makeProductWithDigitalProduct($supplier);
        $this->addGeneralStock($digitalProduct, 2); // only 2 of the 5 needed

        $saleOrder = $this->makeProcessingOrder($product, quantity: 5, digitalProductId: null);

        $this->action->execute();

        $saleOrder->refresh();
        $linkedPo = PurchaseOrder::where('sale_order_id', $saleOrder->id)->first();

        $this->assertEquals(Status::PROCESSING->value, $saleOrder->status);
        $this->assertEquals(2, $saleOrder->items->first()->digitalProducts()->count());
        $this->assertNotNull($linkedPo);
        $this->assertEquals(3, $linkedPo->items->first()->quantity, 'PO covers only the remaining shortfall');
    }

    public function test_running_twice_does_not_create_duplicate_purchase_orders(): void
    {
        Queue::fake();

        $supplier = Supplier::factory()->create(['slug' => 'ez_cards', 'type' => 'external']);
        [$product, $digitalProduct] = $this->makeProductWithDigitalProduct($supplier);

        $saleOrder = $this->makeProcessingOrder($product, quantity: 2, digitalProductId: null);

        $this->action->execute();
        $this->action->execute();

        $this->assertEquals(
            1,
            PurchaseOrder::where('sale_order_id', $saleOrder->id)->count(),
            're-running must not raise a duplicate purchase order'
        );
    }

    public function test_dry_run_makes_no_changes(): void
    {
        Queue::fake();
        Event::fake([SaleOrderCompleted::class]);

        $supplier = Supplier::factory()->create(['type' => 'internal']);
        [$product, $digitalProduct] = $this->makeProductWithDigitalProduct($supplier);
        $this->addGeneralStock($digitalProduct, 3);

        $saleOrder = $this->makeProcessingOrder($product, quantity: 3, digitalProductId: null);

        $summary = $this->action->execute(dryRun: true);

        $saleOrder->refresh();
        $item = $saleOrder->items->first();

        $this->assertEquals(Status::PROCESSING->value, $saleOrder->status, 'status must be untouched');
        $this->assertNull($item->digital_product_id, 'backfill must be rolled back');
        $this->assertEquals(0, $item->digitalProducts()->count(), 'no allocation persisted');
        Event::assertNotDispatched(SaleOrderCompleted::class);

        // The summary still reports what WOULD happen.
        $this->assertEquals(Status::COMPLETED->value, $summary[0]['status']);
        $this->assertEquals(3, $summary[0]['allocated']);
    }

    public function test_item_with_unresolvable_digital_product_is_skipped(): void
    {
        // Product with no attached digital products → digitalProduct() resolves to null.
        $product = Product::factory()->active()->create(['fulfillment_mode' => 'price']);

        $saleOrder = $this->makeProcessingOrder($product, quantity: 2, digitalProductId: null);

        $this->action->execute();

        $saleOrder->refresh();

        $this->assertEquals(Status::PROCESSING->value, $saleOrder->status);
        $this->assertNull($saleOrder->items->first()->digital_product_id);
        $this->assertEquals(0, PurchaseOrder::where('sale_order_id', $saleOrder->id)->count());
    }

    public function test_already_allocated_order_is_marked_completed_without_new_purchase_order(): void
    {
        Event::fake([SaleOrderCompleted::class]);

        $supplier = Supplier::factory()->create(['type' => 'internal']);
        [$product, $digitalProduct] = $this->makeProductWithDigitalProduct($supplier);

        // Order whose units were already allocated under old code but never moved off PROCESSING.
        $saleOrder = SaleOrder::factory()->create(['status' => Status::PROCESSING->value]);
        $item = SaleOrderItem::factory()
            ->forSaleOrder($saleOrder)
            ->forProduct($product)
            ->withQuantity(2)
            ->create(['digital_product_id' => $digitalProduct->id]);

        $this->allocateUnits($item, $digitalProduct, 2);

        $this->action->execute();

        $saleOrder->refresh();

        $this->assertEquals(Status::COMPLETED->value, $saleOrder->status);
        $this->assertEquals(0, PurchaseOrder::where('sale_order_id', $saleOrder->id)->count());
        Event::assertDispatched(SaleOrderCompleted::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @return array{0: Product, 1: DigitalProduct}
     */
    private function makeProductWithDigitalProduct(Supplier $supplier): array
    {
        $product = Product::factory()->active()->create(['fulfillment_mode' => 'price']);
        $digitalProduct = DigitalProduct::factory()->forSupplier($supplier)->create([
            'cost_price' => 10.00,
            'selling_price' => 50.00,
        ]);
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        return [$product, $digitalProduct];
    }

    private function makeProcessingOrder(Product $product, int $quantity, ?int $digitalProductId): SaleOrder
    {
        $saleOrder = SaleOrder::factory()->create(['status' => Status::PROCESSING->value]);

        SaleOrderItem::factory()
            ->forSaleOrder($saleOrder)
            ->forProduct($product)
            ->withQuantity($quantity)
            ->create(['digital_product_id' => $digitalProductId]);

        return $saleOrder;
    }

    /**
     * General stock = available vouchers from a purchase order with no sale order attached.
     */
    private function addGeneralStock(DigitalProduct $digitalProduct, int $count): void
    {
        $purchaseOrder = PurchaseOrder::factory()->completed()->create(); // sale_order_id null by default
        $purchaseOrderItem = PurchaseOrderItem::factory()
            ->forPurchaseOrder($purchaseOrder)
            ->forDigitalProduct($digitalProduct)
            ->withQuantity($count)
            ->create();

        Voucher::factory()->count($count)->create([
            'purchase_order_id' => $purchaseOrder->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'status' => VoucherCodeStatus::AVAILABLE->value,
        ]);
    }

    private function allocateUnits(SaleOrderItem $item, DigitalProduct $digitalProduct, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            SaleOrderItemDigitalProduct::create([
                'sale_order_item_id' => $item->id,
                'digital_product_id' => $digitalProduct->id,
                'digital_product_name' => $digitalProduct->name,
                'digital_product_sku' => $digitalProduct->sku,
                'digital_product_brand' => $digitalProduct->brand,
                'voucher_id' => null,
            ]);
        }
    }
}
