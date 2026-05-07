<?php

namespace Tests\Feature\Commands;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Voucher;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use App\Enums\SaleOrder\Status;
use App\Enums\VoucherCodeStatus;
use App\Models\PurchaseOrderItem;
use App\Events\SaleOrderCompleted;
use App\Models\SaleOrderItemDigitalProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AllocateVouchersToSaleOrderCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a product linked to a fresh digital product via the product_supplier pivot.
     * Returns [product, digitalProduct, purchaseOrder, purchaseOrderItem].
     */
    private function createProductWithDigitalProduct(): array
    {
        $product = Product::factory()->create(['fulfillment_mode' => 'price']);
        $digitalProduct = DigitalProduct::factory()->create();
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $purchaseOrder = PurchaseOrder::factory()->completed()->create();
        $purchaseOrderItem = PurchaseOrderItem::factory()
            ->forPurchaseOrder($purchaseOrder)
            ->forDigitalProduct($digitalProduct)
            ->create();

        return [$product, $digitalProduct, $purchaseOrder, $purchaseOrderItem];
    }

    /**
     * Create $count AVAILABLE vouchers tied to the given purchase order item.
     *
     * @return Voucher[]
     */
    private function createAvailableVouchers(PurchaseOrder $purchaseOrder, PurchaseOrderItem $purchaseOrderItem, int $count): array
    {
        return Voucher::factory()->count($count)->create([
            'purchase_order_id' => $purchaseOrder->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'status' => VoucherCodeStatus::AVAILABLE->value,
        ])->all();
    }

    public function test_fully_allocates_single_item_order(): void
    {
        [$product, , $purchaseOrder, $purchaseOrderItem] = $this->createProductWithDigitalProduct();
        $this->createAvailableVouchers($purchaseOrder, $purchaseOrderItem, 1);

        $saleOrder = SaleOrder::factory()->create(['status' => Status::PROCESSING->value]);
        SaleOrderItem::factory()->forSaleOrder($saleOrder)->forProduct($product)->withQuantity(1)->create();

        $this->artisan('orders:allocate-vouchers', ['sale_order_id' => $saleOrder->id])
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseHas('sale_orders', ['id' => $saleOrder->id, 'status' => Status::COMPLETED->value]);
        $this->assertDatabaseCount('sale_order_item_digital_products', 1);
        $this->assertDatabaseHas('vouchers', [
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'status' => VoucherCodeStatus::ALLOCATED->value,
        ]);
    }

    public function test_fully_allocates_item_with_quantity_greater_than_one(): void
    {
        [$product, , $purchaseOrder, $purchaseOrderItem] = $this->createProductWithDigitalProduct();
        $this->createAvailableVouchers($purchaseOrder, $purchaseOrderItem, 3);

        $saleOrder = SaleOrder::factory()->create(['status' => Status::PROCESSING->value]);
        SaleOrderItem::factory()->forSaleOrder($saleOrder)->forProduct($product)->withQuantity(3)->create();

        $this->artisan('orders:allocate-vouchers', ['sale_order_id' => $saleOrder->id])
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseHas('sale_orders', ['id' => $saleOrder->id, 'status' => Status::COMPLETED->value]);
        $this->assertDatabaseCount('sale_order_item_digital_products', 3);
        $this->assertEquals(
            3,
            Voucher::where('purchase_order_item_id', $purchaseOrderItem->id)
                ->where('status', VoucherCodeStatus::ALLOCATED->value)
                ->count()
        );
    }

    public function test_fully_allocates_order_with_multiple_items(): void
    {
        [$productA, , $purchaseOrderA, $purchaseOrderItemA] = $this->createProductWithDigitalProduct();
        [$productB, , $purchaseOrderB, $purchaseOrderItemB] = $this->createProductWithDigitalProduct();
        $this->createAvailableVouchers($purchaseOrderA, $purchaseOrderItemA, 1);
        $this->createAvailableVouchers($purchaseOrderB, $purchaseOrderItemB, 1);

        $saleOrder = SaleOrder::factory()->create(['status' => Status::PROCESSING->value]);
        $itemA = SaleOrderItem::factory()->forSaleOrder($saleOrder)->forProduct($productA)->withQuantity(1)->create();
        $itemB = SaleOrderItem::factory()->forSaleOrder($saleOrder)->forProduct($productB)->withQuantity(1)->create();

        $this->artisan('orders:allocate-vouchers', ['sale_order_id' => $saleOrder->id])
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseHas('sale_orders', ['id' => $saleOrder->id, 'status' => Status::COMPLETED->value]);
        $this->assertDatabaseCount('sale_order_item_digital_products', 2);
        $this->assertDatabaseHas('sale_order_item_digital_products', ['sale_order_item_id' => $itemA->id]);
        $this->assertDatabaseHas('sale_order_item_digital_products', ['sale_order_item_id' => $itemB->id]);
    }

    public function test_only_allocates_remaining_when_item_is_partially_fulfilled(): void
    {
        [$product, $digitalProduct, $purchaseOrder, $purchaseOrderItem] = $this->createProductWithDigitalProduct();
        // Only 1 new voucher available — the item needs 2 total but already has 1
        $this->createAvailableVouchers($purchaseOrder, $purchaseOrderItem, 1);

        $saleOrder = SaleOrder::factory()->create(['status' => Status::PROCESSING->value]);
        $item = SaleOrderItem::factory()->forSaleOrder($saleOrder)->forProduct($product)->withQuantity(2)->create();

        // Simulate a previously allocated voucher for this item
        SaleOrderItemDigitalProduct::factory()->forSaleOrderItem($item)->forDigitalProduct($digitalProduct)->create();

        $this->artisan('orders:allocate-vouchers', ['sale_order_id' => $saleOrder->id])
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseHas('sale_orders', ['id' => $saleOrder->id, 'status' => Status::COMPLETED->value]);
        // Pre-existing (1) + newly allocated (1) = 2 total
        $this->assertDatabaseCount('sale_order_item_digital_products', 2);
    }

    public function test_rolls_back_and_returns_failure_when_stock_is_insufficient(): void
    {
        [$product, , $purchaseOrder, $purchaseOrderItem] = $this->createProductWithDigitalProduct();
        // Only 1 voucher available but the item needs 2
        $this->createAvailableVouchers($purchaseOrder, $purchaseOrderItem, 1);

        $saleOrder = SaleOrder::factory()->create(['status' => Status::PROCESSING->value]);
        SaleOrderItem::factory()->forSaleOrder($saleOrder)->forProduct($product)->withQuantity(2)->create();

        $this->artisan('orders:allocate-vouchers', ['sale_order_id' => $saleOrder->id])
            ->assertExitCode(Command::FAILURE);

        // Transaction was rolled back — nothing persisted
        $this->assertDatabaseHas('sale_orders', ['id' => $saleOrder->id, 'status' => Status::PROCESSING->value]);
        $this->assertDatabaseCount('sale_order_item_digital_products', 0);
        // Voucher remains available
        $this->assertDatabaseHas('vouchers', [
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'status' => VoucherCodeStatus::AVAILABLE->value,
        ]);
    }

    public function test_skips_and_succeeds_when_order_is_already_completed(): void
    {
        $saleOrder = SaleOrder::factory()->create(['status' => Status::COMPLETED->value]);

        $this->artisan('orders:allocate-vouchers', ['sale_order_id' => $saleOrder->id])
            ->expectsOutput('Sale order is already completed. No allocation needed.')
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseCount('sale_order_item_digital_products', 0);
    }

    public function test_returns_failure_when_sale_order_does_not_exist(): void
    {
        $this->artisan('orders:allocate-vouchers', ['sale_order_id' => 99999])
            ->assertExitCode(Command::FAILURE);
    }

    public function test_fires_sale_order_completed_event_on_full_allocation(): void
    {
        Event::fake();

        [$product, , $purchaseOrder, $purchaseOrderItem] = $this->createProductWithDigitalProduct();
        $this->createAvailableVouchers($purchaseOrder, $purchaseOrderItem, 1);

        $saleOrder = SaleOrder::factory()->create(['status' => Status::PROCESSING->value]);
        SaleOrderItem::factory()->forSaleOrder($saleOrder)->forProduct($product)->withQuantity(1)->create();

        $this->artisan('orders:allocate-vouchers', ['sale_order_id' => $saleOrder->id])
            ->assertExitCode(Command::SUCCESS);

        Event::assertDispatched(SaleOrderCompleted::class, function ($event) use ($saleOrder) {
            return $event->saleOrder->id === $saleOrder->id;
        });
    }

    public function test_does_not_fire_event_when_stock_is_insufficient(): void
    {
        Event::fake();

        [$product, , $purchaseOrder, $purchaseOrderItem] = $this->createProductWithDigitalProduct();
        $this->createAvailableVouchers($purchaseOrder, $purchaseOrderItem, 1);

        $saleOrder = SaleOrder::factory()->create(['status' => Status::PROCESSING->value]);
        SaleOrderItem::factory()->forSaleOrder($saleOrder)->forProduct($product)->withQuantity(2)->create();

        $this->artisan('orders:allocate-vouchers', ['sale_order_id' => $saleOrder->id])
            ->assertExitCode(Command::FAILURE);

        Event::assertNotDispatched(SaleOrderCompleted::class);
    }

    public function test_each_voucher_is_allocated_to_its_own_item_not_a_different_one(): void
    {
        [$productA, , $purchaseOrderA, $purchaseOrderItemA] = $this->createProductWithDigitalProduct();
        [$productB, , $purchaseOrderB, $purchaseOrderItemB] = $this->createProductWithDigitalProduct();
        [$voucherA] = $this->createAvailableVouchers($purchaseOrderA, $purchaseOrderItemA, 1);
        [$voucherB] = $this->createAvailableVouchers($purchaseOrderB, $purchaseOrderItemB, 1);

        $saleOrder = SaleOrder::factory()->create(['status' => Status::PROCESSING->value]);
        $itemA = SaleOrderItem::factory()->forSaleOrder($saleOrder)->forProduct($productA)->withQuantity(1)->create();
        $itemB = SaleOrderItem::factory()->forSaleOrder($saleOrder)->forProduct($productB)->withQuantity(1)->create();

        $this->artisan('orders:allocate-vouchers', ['sale_order_id' => $saleOrder->id])
            ->assertExitCode(Command::SUCCESS);

        // Each voucher must be tied to its corresponding item
        $this->assertDatabaseHas('sale_order_item_digital_products', [
            'sale_order_item_id' => $itemA->id,
            'voucher_id' => $voucherA->id,
        ]);
        $this->assertDatabaseHas('sale_order_item_digital_products', [
            'sale_order_item_id' => $itemB->id,
            'voucher_id' => $voucherB->id,
        ]);

        // Cross-allocations must not exist
        $this->assertDatabaseMissing('sale_order_item_digital_products', [
            'sale_order_item_id' => $itemA->id,
            'voucher_id' => $voucherB->id,
        ]);
        $this->assertDatabaseMissing('sale_order_item_digital_products', [
            'sale_order_item_id' => $itemB->id,
            'voucher_id' => $voucherA->id,
        ]);
    }

    public function test_does_not_consume_vouchers_from_a_different_digital_product(): void
    {
        // Product A has 1 available voucher; product B has none
        [$productA, , $purchaseOrderA, $purchaseOrderItemA] = $this->createProductWithDigitalProduct();
        [$productB] = $this->createProductWithDigitalProduct();
        $this->createAvailableVouchers($purchaseOrderA, $purchaseOrderItemA, 1);

        $saleOrder = SaleOrder::factory()->create(['status' => Status::PROCESSING->value]);
        SaleOrderItem::factory()->forSaleOrder($saleOrder)->forProduct($productA)->withQuantity(1)->create();
        SaleOrderItem::factory()->forSaleOrder($saleOrder)->forProduct($productB)->withQuantity(1)->create();

        // Should fail because product B has no stock, and nothing should be committed
        $this->artisan('orders:allocate-vouchers', ['sale_order_id' => $saleOrder->id])
            ->assertExitCode(Command::FAILURE);

        $this->assertDatabaseCount('sale_order_item_digital_products', 0);
        // Product A's voucher was not consumed
        $this->assertDatabaseHas('vouchers', [
            'purchase_order_item_id' => $purchaseOrderItemA->id,
            'status' => VoucherCodeStatus::AVAILABLE->value,
        ]);
    }
}
