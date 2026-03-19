<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SaleOrderItem;

class DigitalProductAllocationService
{
    public function __construct(
        private VoucherAllocationService $voucherAllocationService,
    ) {}

    /**
     * Allocate as many vouchers as possible for a sale order item.
     * Returns the number of vouchers actually allocated (may be < $quantity).
     */
    public function allocate(SaleOrderItem $item, Product $product, int $quantity): int
    {
        $digitalProduct = $product->digitalProduct();

        if (! $digitalProduct) {
            throw new \Exception("Product {$product->name} has no digital products assigned.");
        }

        $remaining = $quantity;
        if ($remaining <= 0) {
            return 0;
        }

        try {
            $vouchers = $this->voucherAllocationService
                ->getAvailableVouchersForDigitalProduct($digitalProduct->id);

            foreach ($vouchers as $voucher) {
                if ($remaining <= 0) {
                    break;
                }

                $this->voucherAllocationService->allocateVoucher(
                    $item->id,
                    $digitalProduct,
                    $voucher
                );
                $remaining--;
            }
        } catch (\Exception $e) {
            throw $e;
        }

        return $quantity - $remaining;
    }
}
