<?php

namespace Tests\Unit\Actions;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Voucher;
use App\Models\SaleOrder;
use App\Models\PurchaseOrder;
use App\Models\SaleOrderItem;
use App\Models\DigitalProduct;
use App\Enums\SaleOrder\Status;
use App\Enums\VoucherCodeStatus;
use App\Models\PurchaseOrderItem;
use App\Events\SaleOrderCompleted;
use Illuminate\Support\Facades\Event;
use App\Models\SaleOrderItemDigitalProduct;
use App\Actions\AssignVouchersToSaleOrderAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AssignVouchersToSaleOrderActionTest extends TestCase
{
    use RefreshDatabase;

    private AssignVouchersToSaleOrderAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(AssignVouchersToSaleOrderAction::class);
        Event::fake([SaleOrderCompleted::class]);
    }

    /**
     * Create $count AVAILABLE general-stock vouchers (purchase order with no sale order) for the digital product.
     */
    private function makeGeneralStock(DigitalProduct $digitalProduct, int $count): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create(['sale_order_id' => null]);
        $purchaseOrderItem = PurchaseOrderItem::factory()
            ->forPurchaseOrder($purchaseOrder)
            ->forDigitalProduct($digitalProduct)
            ->create();

        Voucher::factory()->count($count)->create([
            'purchase_order_id' => $purchaseOrder->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'status' => VoucherCodeStatus::AVAILABLE->value,
        ]);
    }

    /**
     * Create a PROCESSING sale order with one item that has the given digital product persisted.
     */
    private function makeProcessingOrder(Product $product, ?DigitalProduct $digitalProduct, int $quantity): SaleOrder
    {
        $saleOrder = SaleOrder::factory()->create(['status' => Status::PROCESSING->value]);

        SaleOrderItem::factory()
            ->forSaleOrder($saleOrder)
            ->forProduct($product)
            ->withQuantity($quantity)
            ->create(['digital_product_id' => $digitalProduct?->id]);

        return $saleOrder;
    }

    public function test_fully_allocates_item_using_stored_digital_product_id(): void
    {
        $product = Product::factory()->create();
        $digitalProduct = DigitalProduct::factory()->create();
        $this->makeGeneralStock($digitalProduct, 2);
        $saleOrder = $this->makeProcessingOrder($product, $digitalProduct, 2);

        $result = $this->action->execute($saleOrder);

        $this->assertTrue($result['fully_allocated']);
        $this->assertDatabaseHas('sale_orders', ['id' => $saleOrder->id, 'status' => Status::COMPLETED->value]);
        $this->assertEquals(2, SaleOrderItemDigitalProduct::count());
        $this->assertEquals(2, Voucher::where('status', VoucherCodeStatus::ALLOCATED->value)->count());

        // Allocated against the persisted digital product.
        $this->assertDatabaseHas('sale_order_item_digital_products', ['digital_product_id' => $digitalProduct->id]);
    }

    public function test_uses_stored_digital_product_and_does_not_fall_back_to_another(): void
    {
        $product = Product::factory()->create();
        $selected = DigitalProduct::factory()->create();   // persisted on the item, but has NO stock
        $other = DigitalProduct::factory()->create();       // a different product option WITH stock

        // The product is associated with both; the old code would have fallen back to $other.
        $product->digitalProducts()->attach($selected->id, ['priority' => 1]);
        $product->digitalProducts()->attach($other->id, ['priority' => 2]);

        $this->makeGeneralStock($other, 5);

        $saleOrder = $this->makeProcessingOrder($product, $selected, 1);

        $result = $this->action->execute($saleOrder);

        // No stock for the SELECTED digital product → not fully allocated, nothing committed.
        $this->assertFalse($result['fully_allocated']);
        $this->assertDatabaseHas('sale_orders', ['id' => $saleOrder->id, 'status' => Status::PROCESSING->value]);
        $this->assertEquals(0, SaleOrderItemDigitalProduct::count());

        // The other digital product's stock must remain untouched (no silent fallback).
        $this->assertEquals(5, Voucher::where('status', VoucherCodeStatus::AVAILABLE->value)->count());
    }

    public function test_rolls_back_when_stock_is_insufficient(): void
    {
        $product = Product::factory()->create();
        $digitalProduct = DigitalProduct::factory()->create();
        $this->makeGeneralStock($digitalProduct, 1); // needs 2, only 1 available
        $saleOrder = $this->makeProcessingOrder($product, $digitalProduct, 2);

        $result = $this->action->execute($saleOrder);

        $this->assertFalse($result['fully_allocated']);
        $this->assertDatabaseHas('sale_orders', ['id' => $saleOrder->id, 'status' => Status::PROCESSING->value]);
        $this->assertEquals(0, SaleOrderItemDigitalProduct::count());
        $this->assertEquals(1, Voucher::where('status', VoucherCodeStatus::AVAILABLE->value)->count());
    }

    public function test_returns_already_completed_for_a_completed_order(): void
    {
        $saleOrder = SaleOrder::factory()->create(['status' => Status::COMPLETED->value]);

        $result = $this->action->execute($saleOrder);

        $this->assertTrue($result['already_completed']);
        $this->assertFalse($result['fully_allocated']);
    }

    public function test_item_without_a_selected_digital_product_is_not_allocated(): void
    {
        $product = Product::factory()->create();
        $saleOrder = $this->makeProcessingOrder($product, null, 1);

        $result = $this->action->execute($saleOrder);

        $this->assertFalse($result['fully_allocated']);
        $this->assertDatabaseHas('sale_orders', ['id' => $saleOrder->id, 'status' => Status::PROCESSING->value]);
        $this->assertEquals(0, SaleOrderItemDigitalProduct::count());
    }

    public function test_fires_sale_order_completed_event_on_full_allocation(): void
    {
        $product = Product::factory()->create();
        $digitalProduct = DigitalProduct::factory()->create();
        $this->makeGeneralStock($digitalProduct, 1);
        $saleOrder = $this->makeProcessingOrder($product, $digitalProduct, 1);

        $this->action->execute($saleOrder);

        Event::assertDispatched(SaleOrderCompleted::class, fn ($event) => $event->saleOrder->id === $saleOrder->id);
    }
}
