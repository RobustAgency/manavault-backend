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
     * @return Collection<int, Voucher>
     *
     * @throws \Exception If insufficient vouchers available
     */
    public function getAvailableVouchersForDigitalProduct(int $digitalProductId, ?int $saleOrderId = null): Collection
    {
        return $this->voucherRepository->getAvailableVouchersForDigitalProduct($digitalProductId, $saleOrderId);
    }

    /**
     * Get available quantity (count of unallocated vouchers) for a digital product.
     */
    public function getAvailableQuantity(int $digitalProductId, ?int $saleOrderId = null): int
    {
        return $this->voucherRepository->getAvailableQuantity($digitalProductId, $saleOrderId);
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
