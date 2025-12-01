<?php

namespace App\Repositories;

use App\Models\Voucher;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use App\Services\VoucherCipherService;
use App\Services\VoucherImportService;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Services\PurchaseOrder\PurchaseOrderStatusService;

class VoucherRepository
{
    public function __construct(
        private VoucherImportService $voucherImportService,
        private VoucherCipherService $voucherCipherService,
        private PurchaseOrderStatusService $purchaseOrderStatusService
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

    public function storeVouchers(array $data): bool
    {
        /** @var int $purchaseOrderID */
        $purchaseOrderID = $data['purchase_order_id'];

        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = PurchaseOrder::find($purchaseOrderID);
        $purchaseOrderTotalQuantity = $purchaseOrder->totalQuantityByInternalSupplier();
        $purchaseOrder->load('items.digitalProduct.supplier');

        DB::beginTransaction();
        try {
            if (isset($data['file'])) {
                $this->voucherImportService->processFile($data, $purchaseOrderTotalQuantity);
            } elseif (isset($data['voucher_codes']) && is_array($data['voucher_codes'])) {
                $voucher_numbers = count($data['voucher_codes']);
                if ($voucher_numbers != $purchaseOrderTotalQuantity) {
                    throw new \RuntimeException('The number of voucher codes does not match the total quantity of the purchase order.');
                }
                foreach ($data['voucher_codes'] as $voucherCode) {
                    $code = $this->voucherCipherService->encryptCode($voucherCode['code']);
                    $digitalProductID = $voucherCode['digitalProductID'];
                    $purchaseOrderItem = $purchaseOrder->items->firstWhere('digital_product_id', $digitalProductID);
                    if (! $purchaseOrderItem) {
                        throw new \RuntimeException('Digital product ID '.$digitalProductID.' not found in purchase order items.');
                    }
                    Voucher::create([
                        'code' => $code,
                        'purchase_order_id' => $purchaseOrderID,
                        'purchase_order_item_id' => $purchaseOrderItem->id,
                        'status' => 'available',
                    ]);
                }
            }
            $this->purchaseOrderStatusService->updateStatus($purchaseOrder);
        } catch (\RuntimeException $e) {
            DB::rollBack();
            throw new \RuntimeException($e->getMessage());
        }

        DB::commit();

        return true;
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
