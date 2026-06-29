<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Voucher;
use App\Models\SaleOrderItem;
use App\Enums\VoucherFulfillmentStatus;
use App\Models\SaleOrderItemDigitalProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SaleOrderItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_fulfillment_is_pending_when_no_vouchers_are_allocated(): void
    {
        $item = SaleOrderItem::factory()->withQuantity(2)->create();

        $item = $item->fresh();
        $this->assertSame(0, $item->allocatedVoucherCount());
        $this->assertFalse($item->isFullyFulfilled());
        $this->assertSame(VoucherFulfillmentStatus::PENDING, $item->voucherFulfillmentStatus());
    }

    public function test_fulfillment_is_pending_when_only_partially_allocated(): void
    {
        $item = SaleOrderItem::factory()->withQuantity(2)->create();
        $this->allocate($item, 1);

        $item = $item->fresh();
        $this->assertSame(1, $item->allocatedVoucherCount());
        $this->assertFalse($item->isFullyFulfilled());
        $this->assertSame(VoucherFulfillmentStatus::PENDING, $item->voucherFulfillmentStatus());
    }

    public function test_fulfillment_is_completed_when_vouchers_cover_the_full_quantity(): void
    {
        $item = SaleOrderItem::factory()->withQuantity(2)->create();
        $this->allocate($item, 2);

        $item = $item->fresh();
        $this->assertSame(2, $item->allocatedVoucherCount());
        $this->assertTrue($item->isFullyFulfilled());
        $this->assertSame(VoucherFulfillmentStatus::COMPLETED, $item->voucherFulfillmentStatus());
    }

    public function test_allocations_without_a_voucher_do_not_count_toward_fulfillment(): void
    {
        $item = SaleOrderItem::factory()->withQuantity(1)->create();
        SaleOrderItemDigitalProduct::factory()
            ->forSaleOrderItem($item)
            ->create(['voucher_id' => null]);

        $item = $item->fresh();
        $this->assertSame(0, $item->allocatedVoucherCount());
        $this->assertSame(VoucherFulfillmentStatus::PENDING, $item->voucherFulfillmentStatus());
    }

    /**
     * Create $count voucher-backed allocations against the item.
     */
    private function allocate(SaleOrderItem $item, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            SaleOrderItemDigitalProduct::factory()
                ->forSaleOrderItem($item)
                ->create(['voucher_id' => Voucher::factory()->create()->id]);
        }
    }
}
