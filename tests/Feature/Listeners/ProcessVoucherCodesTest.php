<?php

namespace Tests\Feature\Listeners;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Voucher;
use App\Models\SaleOrder;
use App\Models\PurchaseOrder;
use App\Models\SaleOrderItem;
use App\Enums\SaleOrderStatus;
use App\Models\DigitalProduct;
use App\Enums\VoucherCodeStatus;
use App\Events\SaleOrderUpdated;
use App\Models\PurchaseOrderItem;
use App\Events\NewVouchersAvailable;
use Illuminate\Support\Facades\Event;
use App\Listeners\ProcessVoucherCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * The invariant under test: when vouchers arrive for a sale order, each item must be
 * allocated exactly its ordered quantity — never more (even with surplus stock or
 * repeated events), and never fewer than the available stock allows.
 */
class ProcessVoucherCodesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([SaleOrderUpdated::class]);
    }

    public function test_assigns_exactly_the_item_quantity_when_stock_matches_demand(): void
    {
        $order = $this->makeLinkedOrder(itemQuantity: 3, availableVouchers: 3);
        $item = $order['item'];
        $poItem = $order['poItem'];

        app(ProcessVoucherCodes::class)->handle(new NewVouchersAvailable($poItem));

        $this->assertSame(3, $item->digitalProducts()->count());
        $this->assertSame(0, Voucher::where('status', VoucherCodeStatus::AVAILABLE->value)->count());
        $this->assertSame(3, Voucher::where('status', VoucherCodeStatus::ALLOCATED->value)->count());
    }

    public function test_assigns_no_more_than_the_item_quantity_when_surplus_stock_exists(): void
    {
        $order = $this->makeLinkedOrder(itemQuantity: 2, availableVouchers: 5);
        $item = $order['item'];
        $poItem = $order['poItem'];

        app(ProcessVoucherCodes::class)->handle(new NewVouchersAvailable($poItem));

        // Exactly the ordered quantity is consumed; the surplus stays available.
        $this->assertSame(2, $item->digitalProducts()->count());
        $this->assertSame(3, Voucher::where('status', VoucherCodeStatus::AVAILABLE->value)->count());
    }

    public function test_assigns_all_available_when_stock_is_short(): void
    {
        $order = $this->makeLinkedOrder(itemQuantity: 5, availableVouchers: 2);
        $saleOrder = $order['saleOrder'];
        $item = $order['item'];
        $poItem = $order['poItem'];

        app(ProcessVoucherCodes::class)->handle(new NewVouchersAvailable($poItem));

        $this->assertSame(2, $item->digitalProducts()->count());
        $this->assertSame(0, Voucher::where('status', VoucherCodeStatus::AVAILABLE->value)->count());

        $this->assertSame(SaleOrderStatus::PROCESSING->value, $saleOrder->refresh()->status);
    }

    public function test_does_not_over_allocate_when_handled_repeatedly(): void
    {
        $order = $this->makeLinkedOrder(itemQuantity: 2, availableVouchers: 5);
        $item = $order['item'];
        $poItem = $order['poItem'];

        $event = new NewVouchersAvailable($poItem);
        app(ProcessVoucherCodes::class)->handle($event);
        app(ProcessVoucherCodes::class)->handle($event);

        $this->assertSame(2, $item->digitalProducts()->count());
    }

    public function test_allocates_each_item_to_exactly_its_own_quantity(): void
    {
        $saleOrder = SaleOrder::factory()->create(['status' => SaleOrderStatus::PROCESSING->value]);

        $a = $this->addLinkedItem($saleOrder, quantity: 2, availableVouchers: 4);
        $b = $this->addLinkedItem($saleOrder, quantity: 1, availableVouchers: 1);

        app(ProcessVoucherCodes::class)->handle(new NewVouchersAvailable($a['poItem']));

        $this->assertSame(2, $a['item']->digitalProducts()->count());
        $this->assertSame(1, $b['item']->digitalProducts()->count());
    }

    /**
     * Build a single-item sale order with a linked, completed purchase order carrying the
     * given number of available vouchers.
     *
     * @return array{saleOrder: SaleOrder, item: SaleOrderItem, poItem: PurchaseOrderItem}
     */
    private function makeLinkedOrder(int $itemQuantity, int $availableVouchers): array
    {
        $saleOrder = SaleOrder::factory()->create(['status' => SaleOrderStatus::PROCESSING->value]);
        $linked = $this->addLinkedItem($saleOrder, $itemQuantity, $availableVouchers);

        return [
            'saleOrder' => $saleOrder,
            'item' => $linked['item'],
            'poItem' => $linked['poItem'],
        ];
    }

    /**
     * Attach an item to the sale order, backed by a linked PO holding $availableVouchers
     * available vouchers for that item's digital product.
     *
     * @return array{item: SaleOrderItem, poItem: PurchaseOrderItem}
     */
    private function addLinkedItem(SaleOrder $saleOrder, int $quantity, int $availableVouchers): array
    {
        $digitalProduct = DigitalProduct::factory()->create();

        $product = Product::factory()->active()->create(['fulfillment_mode' => 'price']);
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $item = SaleOrderItem::factory()
            ->forSaleOrder($saleOrder)
            ->forProduct($product)
            ->withQuantity($quantity)
            ->create(['digital_product_id' => $digitalProduct->id]);

        // A shortfall PO is linked to the sale order via sale_order_id.
        $purchaseOrder = PurchaseOrder::factory()->completed()->create([
            'sale_order_id' => $saleOrder->id,
        ]);
        $poItem = PurchaseOrderItem::factory()
            ->forPurchaseOrder($purchaseOrder)
            ->forDigitalProduct($digitalProduct)
            ->withQuantity(max($availableVouchers, 1))
            ->create();

        if ($availableVouchers > 0) {
            Voucher::factory()->count($availableVouchers)->create([
                'purchase_order_id' => $purchaseOrder->id,
                'purchase_order_item_id' => $poItem->id,
                'status' => VoucherCodeStatus::AVAILABLE->value,
            ]);
        }

        return ['item' => $item, 'poItem' => $poItem];
    }

    public function test_listener_is_guarded_by_a_without_overlapping_lock(): void
    {
        $saleOrder = SaleOrder::factory()->create();
        $purchaseOrder = PurchaseOrder::factory()->create(['sale_order_id' => $saleOrder->id]);
        $poItem = PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->create();

        $middleware = app(ProcessVoucherCodes::class)
            ->middleware(new NewVouchersAvailable($poItem));

        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);

        // Keyed per sale order: handle() allocates across the whole order, so all of
        // an order's voucher events must serialize. Change only if that scope changes.
        $this->assertSame("process-voucher-codes:{$saleOrder->id}", $middleware[0]->key);

        // A contended job is released for retry (not dropped), and a dead worker's
        // lock self-expires so the order can't get stuck.
        $this->assertSame(10, $middleware[0]->releaseAfter);
        $this->assertSame(120, $middleware[0]->expiresAfter);
    }

    public function test_no_lock_is_applied_when_the_purchase_order_has_no_sale_order(): void
    {
        // Manual purchase order: no sale order, so handle() no-ops and there is
        // nothing to serialize — the listener must not pile onto a shared lock.
        $purchaseOrder = PurchaseOrder::factory()->create(['sale_order_id' => null]);
        $poItem = PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->create();

        $middleware = app(ProcessVoucherCodes::class)
            ->middleware(new NewVouchersAvailable($poItem));

        $this->assertSame([], $middleware);
    }
}
