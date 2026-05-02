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

    /**
     * @return Collection<int, \App\Models\Voucher>
     */
    public function getAvailableVouchersForDigitalProduct(int $digitalProductId, ?int $saleOrderId = null): Collection
    {
        $vouchers = $this->availableVouchersQuery($digitalProductId, $saleOrderId)
            ->orderBy('created_at')
            ->lockForUpdate()
            ->get();

        /** @var Collection<int, \App\Models\Voucher> $keyed */
        $keyed = $vouchers->keyBy('id');

        return $keyed;
    }

    /**
     * Get available quantity (count of unallocated vouchers) for a digital product.
     */
    public function getAvailableQuantity(int $digitalProductId, ?int $saleOrderId = null): int
    {
        return $this->availableVouchersQuery($digitalProductId, $saleOrderId)->count();
    }

    public function updateVoucherStatus(int $voucherId, string $status): void
    {
        $voucher = Voucher::findOrFail($voucherId);
        $voucher->status = $status;
        $voucher->save();
    }

    /**
     * Get a query builder for available vouchers of a digital product.
     *
     * @return Builder<Voucher>
     */
    private function availableVouchersQuery(int $digitalProductId, ?int $saleOrderId = null): Builder
    {
        $query = Voucher::query()
            ->where('status', VoucherCodeStatus::AVAILABLE->value)
            ->whereHas('purchaseOrderItem', function ($q) use ($digitalProductId) {
                $q->where('digital_product_id', $digitalProductId);
            });

        if ($saleOrderId !== null) {
            $query->whereHas('purchaseOrder', function ($q) use ($saleOrderId) {
                $q->where('sale_order_id', $saleOrderId);
            });
        }

        return $query;
    }
}
