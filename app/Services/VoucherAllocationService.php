<?php

namespace App\Services;

use App\Models\Voucher;
use App\Models\DigitalProduct;
use App\Enums\VoucherCodeStatus;
use Illuminate\Support\Collection;
use App\Repositories\VoucherRepository;
use App\Repositories\SaleOrderItemDigitalProductRepository;

class VoucherAllocationService
{
    public function __construct(
        private VoucherRepository $voucherRepository,
        private SaleOrderItemDigitalProductRepository $saleOrderItemDigitalProductRepository
    ) {}

    /**
     * Vouchers from purchase orders that have no sale order attached (general stock).
     *
     * @return Collection<int, Voucher>
     */
    public function getAvailableVouchers(int $digitalProductId): Collection
    {
        return $this->voucherRepository->getAvailableVouchers($digitalProductId);
    }

    /**
     * Vouchers from purchase orders that belong to the given sale order.
     *
     * @return Collection<int, Voucher>
     */
    public function getAvailableVouchersForSaleOrder(int $digitalProductId, int $saleOrderId): Collection
    {
        return $this->voucherRepository->getAvailableVouchersForSaleOrder($digitalProductId, $saleOrderId);
    }

    /**
     * Get count of unallocated vouchers (general stock only) for a digital product.
     */
    public function getAvailableQuantity(int $digitalProductId): int
    {
        return $this->voucherRepository->getAvailableVouchersCount($digitalProductId);
    }

    public function allocateVoucher(int $saleOrderItemId, DigitalProduct $digitalProduct, Voucher $voucher): void
    {
        $this->saleOrderItemDigitalProductRepository->allocateVoucher(
            $saleOrderItemId,
            $digitalProduct->id,
            $voucher->id,
            $digitalProduct->name,
            $digitalProduct->sku,
            $digitalProduct->brand,
        );

        $this->voucherRepository->updateVoucherStatus($voucher->id, VoucherCodeStatus::ALLOCATED->value);
    }
}
