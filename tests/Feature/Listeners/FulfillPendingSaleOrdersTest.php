<?php

namespace Tests\Feature\Listeners;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SaleOrder;
use App\Models\PurchaseOrder;
use App\Models\SaleOrderItem;
use App\Models\DigitalProduct;
use App\Enums\SaleOrder\Status;
use App\Enums\VoucherCodeStatus;
use App\Models\PurchaseOrderItem;
use App\Events\SaleOrderCompleted;
use App\Events\NewVouchersAvailable;
use Illuminate\Support\Facades\Event;
use App\Listeners\FulfillPendingSaleOrders;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FulfillPendingSaleOrdersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper — create a PROCESSING sale order with a given number of allocated vouchers
     * and a remaining shortfall.
     */
    private function createProcessingSaleOrder(
        Product $product,
        DigitalProduct $digitalProduct,
        int $quantity,
        int $alreadyAllocated
    ): SaleOrder {
        $saleOrder = SaleOrder::factory()->create([
            'status' => Status::PROCESSING->value,
            'source' => SaleOrder::MANASTORE,
        ]);

        $item = SaleOrderItem::factory()->create([
            'sale_order_id' => $saleOrder->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $product->selling_price,
            'subtotal' => $quantity * $product->selling_price,
        ]);

        // Simulate already-allocated vouchers
        for ($i = 0; $i < $alreadyAllocated; $i++) {
            $po = PurchaseOrder::factory()->completed()->create();
            $poi = PurchaseOrderItem::factory()
                ->forPurchaseOrder($po)
                ->forDigitalProduct($digitalProduct)
                ->withQuantity(1)
                ->create();

            $voucher = \App\Models\Voucher::factory()->create([
                'purchase_order_id' => $po->id,
                'purchase_order_item_id' => $poi->id,
                'status' => VoucherCodeStatus::ALLOCATED->value,
            ]);

            $item->digitalProducts()->attach($digitalProduct->id, [
                'voucher_id' => $voucher->id,
            ]);
        }

        return $saleOrder;
    }

    /**
     * NewVouchersAvailable event triggers the listener and re-attempts allocation.
     */
    public function test_listener_is_triggered_by_new_vouchers_available_event(): void
    {
        Event::fake([SaleOrderCompleted::class]);

        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = Product::factory()->active()->create(['fulfillment_mode' => 'price', 'selling_price' => 10.00]);
        $digitalProduct = DigitalProduct::factory()->forSupplier($supplier)->create(['selling_price' => 10.00]);
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        // PROCESSING order — needs 2 vouchers, has 0 allocated
        $saleOrder = $this->createProcessingSaleOrder($product, $digitalProduct, 2, 0);

        // Add 2 fresh vouchers (simulating new stock arriving)
        $po = PurchaseOrder::factory()->completed()->create();
        $poi = PurchaseOrderItem::factory()->forPurchaseOrder($po)->forDigitalProduct($digitalProduct)->withQuantity(2)->create();
        \App\Models\Voucher::factory()->count(2)->create([
            'purchase_order_id' => $po->id,
            'purchase_order_item_id' => $poi->id,
            'status' => VoucherCodeStatus::AVAILABLE->value,
        ]);

        // Dispatch the event — the listener runs synchronously in tests
        event(new NewVouchersAvailable([$digitalProduct->id]));

        $saleOrder->refresh();
        $this->assertEquals(Status::COMPLETED->value, $saleOrder->status);
        Event::assertDispatched(SaleOrderCompleted::class, fn ($e) => $e->saleOrder->id === $saleOrder->id);
    }

    /**
     * Sufficient new stock → order transitions PROCESSING → COMPLETED, SaleOrderCompleted fired.
     */
    public function test_sufficient_new_stock_completes_processing_order(): void
    {
        Event::fake([SaleOrderCompleted::class]);

        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = Product::factory()->active()->create(['fulfillment_mode' => 'price', 'selling_price' => 10.00]);
        $digitalProduct = DigitalProduct::factory()->forSupplier($supplier)->create(['selling_price' => 10.00]);
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        // Order needs 3, has 1 already allocated
        $saleOrder = $this->createProcessingSaleOrder($product, $digitalProduct, 3, 1);

        // 2 more vouchers arrive
        $po = PurchaseOrder::factory()->completed()->create();
        $poi = PurchaseOrderItem::factory()->forPurchaseOrder($po)->forDigitalProduct($digitalProduct)->withQuantity(2)->create();
        \App\Models\Voucher::factory()->count(2)->create([
            'purchase_order_id' => $po->id,
            'purchase_order_item_id' => $poi->id,
            'status' => VoucherCodeStatus::AVAILABLE->value,
        ]);

        $listener = app(FulfillPendingSaleOrders::class);
        $listener->handle(new NewVouchersAvailable([$digitalProduct->id]));

        $saleOrder->refresh();
        $this->assertEquals(Status::COMPLETED->value, $saleOrder->status);
        Event::assertDispatched(SaleOrderCompleted::class, fn ($e) => $e->saleOrder->id === $saleOrder->id);
    }

    /**
     * Still insufficient stock → order stays PROCESSING.
     */
    public function test_still_insufficient_stock_keeps_order_processing(): void
    {
        Event::fake([SaleOrderCompleted::class]);

        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = Product::factory()->active()->create(['fulfillment_mode' => 'price', 'selling_price' => 10.00]);
        $digitalProduct = DigitalProduct::factory()->forSupplier($supplier)->create(['selling_price' => 10.00]);
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        // Order needs 5, has 0 allocated — only 2 new vouchers arrive (still short)
        $saleOrder = $this->createProcessingSaleOrder($product, $digitalProduct, 5, 0);

        $po = PurchaseOrder::factory()->completed()->create();
        $poi = PurchaseOrderItem::factory()->forPurchaseOrder($po)->forDigitalProduct($digitalProduct)->withQuantity(2)->create();
        \App\Models\Voucher::factory()->count(2)->create([
            'purchase_order_id' => $po->id,
            'purchase_order_item_id' => $poi->id,
            'status' => VoucherCodeStatus::AVAILABLE->value,
        ]);

        $listener = app(FulfillPendingSaleOrders::class);
        $listener->handle(new NewVouchersAvailable([$digitalProduct->id]));

        $saleOrder->refresh();
        $this->assertEquals(Status::PROCESSING->value, $saleOrder->status);
        Event::assertNotDispatched(SaleOrderCompleted::class);
    }

    /**
     * Multiple PROCESSING orders — each retried independently.
     */
    public function test_multiple_processing_orders_retried_independently(): void
    {
        Event::fake([SaleOrderCompleted::class]);

        $supplier = Supplier::factory()->create(['type' => 'internal']);
        $product = Product::factory()->active()->create(['fulfillment_mode' => 'price', 'selling_price' => 10.00]);
        $digitalProduct = DigitalProduct::factory()->forSupplier($supplier)->create(['selling_price' => 10.00]);
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        // Order A — needs 2, will be completable
        $orderA = $this->createProcessingSaleOrder($product, $digitalProduct, 2, 0);
        // Order B — needs 5, will remain short
        $orderB = $this->createProcessingSaleOrder($product, $digitalProduct, 5, 0);

        // Add exactly 2 vouchers — enough for A, not for B
        $po = PurchaseOrder::factory()->completed()->create();
        $poi = PurchaseOrderItem::factory()->forPurchaseOrder($po)->forDigitalProduct($digitalProduct)->withQuantity(2)->create();
        \App\Models\Voucher::factory()->count(2)->create([
            'purchase_order_id' => $po->id,
            'purchase_order_item_id' => $poi->id,
            'status' => VoucherCodeStatus::AVAILABLE->value,
        ]);

        $listener = app(FulfillPendingSaleOrders::class);
        $listener->handle(new NewVouchersAvailable([$digitalProduct->id]));

        $orderA->refresh();
        $orderB->refresh();

        $this->assertEquals(Status::COMPLETED->value, $orderA->status);
        $this->assertEquals(Status::PROCESSING->value, $orderB->status);

        Event::assertDispatched(SaleOrderCompleted::class, fn ($e) => $e->saleOrder->id === $orderA->id);
        Event::assertNotDispatched(SaleOrderCompleted::class, fn ($e) => $e->saleOrder->id === $orderB->id);
    }
}
