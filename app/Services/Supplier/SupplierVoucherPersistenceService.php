<?php

namespace App\Services\Supplier;

use App\DTOs\Supplier\VoucherData;
use App\Events\NewVouchersAvailable;
use App\Models\PurchaseOrder;
use App\Models\Voucher;
use App\Services\Voucher\VoucherCipherService;

class SupplierVoucherPersistenceService
{
    public function __construct(
        private readonly VoucherCipherService $cipherService,
    ) {}

    /**
     * Persist an array of VoucherData DTOs as Voucher records and fire the
     * NewVouchersAvailable event once for all affected digital products.
     *
     * Dedup strategy (in priority order):
     *   1. code_hash  — SHA-256 of the plaintext code, checked against the
     *                   unique (purchase_order_id, code_hash) DB constraint.
     *                   Covers every voucher that has a non-empty code.
     *   2. serial_number — fallback for any edge case where code is absent
     *                      but a serial number is present.
     *
     * @param  VoucherData[]  $vouchers
     * @return int  number of records created
     */
    public function persist(PurchaseOrder $po, array $vouchers): int
    {
        if (empty($vouchers)) {
            return 0;
        }

        $digitalProductIds = [];
        $created = 0;

        foreach ($vouchers as $vd) {
            $codeHash = $vd->code !== '' ? hash('sha256', $vd->code) : null;

            if ($this->isDuplicate($po->id, $codeHash, $vd->serialNumber)) {
                continue;
            }

            $encryptedCode = $codeHash !== null
                ? $this->cipherService->encryptCode($vd->code)
                : null;

            Voucher::create([
                'purchase_order_id'      => $po->id,
                'purchase_order_item_id' => $vd->purchaseOrderItemId,
                'code'                   => $encryptedCode,
                'code_hash'              => $codeHash,
                'serial_number'          => $vd->serialNumber,
                'pin_code'               => $vd->pinCode,
                'status'                 => 'available',
            ]);

            $digitalProductId = $po->items->firstWhere('id', $vd->purchaseOrderItemId)?->digital_product_id;

            if ($digitalProductId) {
                $digitalProductIds[] = $digitalProductId;
            }

            $created++;
        }

        if ($created > 0) {
            event(new NewVouchersAvailable(array_values(array_unique($digitalProductIds))));
        }

        return $created;
    }

    /**
     * Returns true if a voucher with the same code or serial already exists
     * for this purchase order, preventing double-storage.
     */
    private function isDuplicate(int $purchaseOrderId, ?string $codeHash, ?string $serialNumber): bool
    {
        if ($codeHash !== null) {
            return Voucher::where('purchase_order_id', $purchaseOrderId)
                ->where('code_hash', $codeHash)
                ->exists();
        }

        if ($serialNumber !== null) {
            return Voucher::where('purchase_order_id', $purchaseOrderId)
                ->where('serial_number', $serialNumber)
                ->exists();
        }

        return false;
    }
}
