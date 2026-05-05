<?php

namespace App\Services;

use App\Models\Product;
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
    public function allocateFromGeneralStock(SaleOrderItem $item, Product $product, int $quantity): int
    {
        $digitalProduct = $this->resolveDigitalProduct($product);
        $vouchers = $this->voucherAllocationService->getAvailableVouchers($digitalProduct->id);

        return $this->performAllocation($item, $digitalProduct, $vouchers, $quantity);
    }

    /**
     * Allocate vouchers from a purchase order linked to a specific sale order.
     * Returns the number of vouchers actually allocated (may be < $quantity).
     */
    public function allocateFromLinkedPurchaseOrder(SaleOrderItem $item, Product $product, int $quantity, int $saleOrderId): int
    {
        $digitalProduct = $this->resolveDigitalProduct($product);
        $vouchers = $this->voucherAllocationService->getAvailableVouchersForSaleOrder($digitalProduct->id, $saleOrderId);

        return $this->performAllocation($item, $digitalProduct, $vouchers, $quantity);
    }

    private function resolveDigitalProduct(Product $product): DigitalProduct
    {
        $digitalProduct = $product->digitalProduct();

        return $digitalProduct;
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
