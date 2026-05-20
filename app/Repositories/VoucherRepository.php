<?php

namespace App\Repositories;

use App\Models\Voucher;
use App\Enums\VoucherCodeStatus;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use App\Services\Voucher\VoucherCipherService;
use Illuminate\Pagination\LengthAwarePaginator;

class VoucherRepository
{
    public function __construct(
        private VoucherCipherService $voucherCipherService,
    ) {}

    /**
     * Get paginated vouchers filtered by the provided criteria.
     *
     * @return LengthAwarePaginator<int, Voucher>
     */
    public function getFilteredVouchers(array $filters): LengthAwarePaginator
    {
        $query = Voucher::query();

        if (isset($filters['purchase_order_id'])) {
            $query->where('purchase_order_id', $filters['purchase_order_id']);
        }

        $perPage = $filters['per_page'] ?? 10;

        return $query->paginate($perPage);
    }

    public function showVoucherCode(Voucher $voucher): string
    {
        $decryptedCode = $this->decryptVoucherCode($voucher);

        return $decryptedCode;
    }

    public function decryptVoucherCode(Voucher $voucher): string
    {
        // Check if the code is encrypted before attempting to decrypt
        if ($this->voucherCipherService->isEncrypted($voucher->code)) {
            return $this->voucherCipherService->decryptCode($voucher->code);
        }

        // If not encrypted (legacy plain text), return as-is
        return $voucher->code;
    }

    public function updateVoucherStatus(int $voucherId, string $status): void
    {
        $voucher = Voucher::findOrFail($voucherId);
        $voucher->status = $status;
        $voucher->save();
    }

    /**
     * Vouchers from purchase orders that have no sale order attached.
     *
     * @return Collection<int, \App\Models\Voucher>
     */
    public function getAvailableVouchers(int $digitalProductId): Collection
    {
        $vouchers = $this->baseAvailableVouchersQuery($digitalProductId)
            ->whereHas('purchaseOrder', fn ($q) => $q->whereNull('sale_order_id'))
            ->orderBy('created_at')
            ->lockForUpdate()
            ->get();

        /** @var Collection<int, \App\Models\Voucher> $keyed */
        $keyed = $vouchers->keyBy('id');

        return $keyed;
    }

    /**
     * Count of vouchers from purchase orders that have no sale order attached.
     */
    public function getAvailableVouchersCount(int $digitalProductId): int
    {
        return $this->baseAvailableVouchersQuery($digitalProductId)
            ->whereHas('purchaseOrder', fn ($q) => $q->whereNull('sale_order_id'))
            ->count();
    }

    /**
     * Vouchers from purchase orders that belong to the given sale order.
     *
     * @return Collection<int, \App\Models\Voucher>
     */
    public function getAvailableVouchersForSaleOrder(int $digitalProductId, int $saleOrderId): Collection
    {
        $vouchers = $this->baseAvailableVouchersQuery($digitalProductId)
            ->whereHas('purchaseOrder', fn ($q) => $q->where('sale_order_id', $saleOrderId))
            ->orderBy('created_at')
            ->lockForUpdate()
            ->get();

        /** @var Collection<int, \App\Models\Voucher> $keyed */
        $keyed = $vouchers->keyBy('id');

        return $keyed;
    }

    /**
     * @return Builder<Voucher>
     */
    private function baseAvailableVouchersQuery(int $digitalProductId): Builder
    {
        return Voucher::query()
            ->where('status', VoucherCodeStatus::AVAILABLE->value)
            ->whereHas('purchaseOrderItem', fn ($q) => $q->where('digital_product_id', $digitalProductId));
    }
}
