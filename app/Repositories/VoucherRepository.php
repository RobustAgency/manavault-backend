<?php

namespace App\Repositories;

use App\Services\VoucherImportService;
use App\Models\Voucher;
use Illuminate\Pagination\LengthAwarePaginator;

class VoucherRepository
{
    public function __construct(private VoucherImportService $voucherImportService) {}

    /**
     * Get paginated vouchers filtered by the provided criteria.
     * @param array $filters
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
        return $this->voucherImportService->processFile($data);
    }
}
