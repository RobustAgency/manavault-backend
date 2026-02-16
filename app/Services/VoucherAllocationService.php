<?php

namespace App\Services;

use App\Models\Voucher;
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
     * @param  int  $quantity  Number of vouchers to fetch
     * @return Collection<int, Voucher>
     *
     * @throws \Exception If insufficient vouchers available
     */
    public function getAvailableVouchersForDigitalProduct(int $digitalProductId, int $quantity): Collection
    {
        return $this->voucherRepository->getAvailableVouchersForDigitalProduct($digitalProductId, $quantity);
    }

    /**
     * Get available quantity (count of unallocated vouchers) for a digital product.
     */
    public function getAvailableQuantity(int $digitalProductId): int
    {
        return $this->voucherRepository->getAvailableQuantity($digitalProductId);
    }

    public function allocateVoucher(int $saleOrderItemId, int $digitalProductId, Voucher $voucher): void
    {
        $this->saleOrderItemDigitalProductRepository->allocateVoucher(
            $saleOrderItemId,
            $digitalProductId,
            $voucher->id
        );

        $this->voucherRepository->updateVoucherStatus($voucher->id, VoucherCodeStatus::ALLOCATED->value);
    }
}
