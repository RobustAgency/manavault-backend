<?php

namespace App\Repositories;

use App\Models\Voucher;
use App\Services\VoucherImportService;
use Illuminate\Pagination\LengthAwarePaginator;

class VoucherRepository
{
    public function __construct(private VoucherImportService $voucherImportService) {}

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

    public function importVouchers(array $data): bool
    {
        try {
            $this->voucherImportService->processFile($data);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException($e->getMessage());
        }

        return true;
    }
}
