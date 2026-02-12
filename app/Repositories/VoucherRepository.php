<?php

namespace App\Repositories;

use App\Models\Voucher;
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
}
