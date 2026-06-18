<?php

namespace App\Services;

use App\Models\Voucher;
use App\Models\SaleOrderItem;
use App\Models\DigitalProduct;
use Illuminate\Support\Collection;

class DigitalProductAllocationService
{
    public function __construct(
        private VoucherAllocationService $voucherAllocationService,
    ) {}

    /**
     * Allocate vouchers from general stock (purchase orders with no sale order attached).
     * Returns the number of vouchers actually allocated (may be < $quantity).
     */
    public function allocateFromGeneralStock(SaleOrderItem $item, ?DigitalProduct $digitalProduct, int $quantity): int
    {
        if ($digitalProduct === null) {
            return 0;
        }

        $vouchers = $this->voucherAllocationService->getAvailableVouchers($digitalProduct->id);

        return $this->performAllocation($item, $digitalProduct, $vouchers, $quantity);
    }

    /**
     * Allocate vouchers from a purchase order linked to a specific sale order.
     * Returns the number of vouchers actually allocated (may be < $quantity).
     *
     * The digital product is the one selected for the item at order creation time, not a
     * live resolution of the Product → DigitalProduct association (which is mutable).
     */
    public function allocateFromLinkedPurchaseOrder(SaleOrderItem $item, ?DigitalProduct $digitalProduct, int $quantity, int $saleOrderId): int
    {
        if ($digitalProduct === null) {
            return 0;
        }

        $vouchers = $this->voucherAllocationService->getAvailableVouchersForSaleOrder($digitalProduct->id, $saleOrderId);

        return $this->performAllocation($item, $digitalProduct, $vouchers, $quantity);
    }

    /**
     * @param  Collection<int, Voucher>  $vouchers
     */
    private function performAllocation(SaleOrderItem $item, DigitalProduct $digitalProduct, Collection $vouchers, int $quantity): int
    {
        $remaining = $quantity;

        foreach ($vouchers as $voucher) {
            if ($remaining <= 0) {
                break;
            }

            $this->voucherAllocationService->allocateVoucher($item->id, $digitalProduct, $voucher);
            $remaining--;
        }

        return $quantity - $remaining;
    }
}
