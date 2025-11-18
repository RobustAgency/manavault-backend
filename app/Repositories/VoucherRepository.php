<?php

namespace App\Repositories;

use App\Models\Voucher;
use App\Models\PurchaseOrder;
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

    public function storeVouchers(array $data): bool
    {
        /** @var int $purchaseOrderID */
        $purchaseOrderID = $data['purchase_order_id'];

        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = PurchaseOrder::find($purchaseOrderID);
        $purchaseOrderTotalQuantity = $purchaseOrder->getTotalQuantity();

        try {
            if (isset($data['file'])) {
                $this->voucherImportService->processFile($data, $purchaseOrderTotalQuantity);
            } elseif (isset($data['voucher_codes']) && is_array($data['voucher_codes'])) {
                $voucher_numbers = count($data['voucher_codes']);
                if ($voucher_numbers != $purchaseOrderTotalQuantity) {
                    throw new \RuntimeException('The number of voucher codes does not match the total quantity of the purchase order.');
                }
                foreach ($data['voucher_codes'] as $code) {
                    Voucher::create([
                        'code' => $code,
                        'purchase_order_id' => $purchaseOrderID,
                        'status' => 'available',
                    ]);
                }
            }
        } catch (\RuntimeException $e) {
            throw new \RuntimeException($e->getMessage());
        }

        return true;
    }
}
