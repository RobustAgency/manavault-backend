<?php

namespace App\Suppliers\Support;

use App\Models\Voucher;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use App\Events\NewVouchersAvailable;
use App\Services\Voucher\VoucherCipherService;

class VoucherWriter
{
    public function __construct(private VoucherCipherService $cipher) {}

    /**
     * Persist voucher drafts for a purchase order, encrypting codes and skipping
     * duplicates as declared by VoucherDraft::$dedupeBy. Fires NewVouchersAvailable
     * once per call when at least one voucher was inserted.
     *
     * @param  array<int, VoucherDraft>  $drafts
     * @return int Number of vouchers actually inserted
     */
    public function store(PurchaseOrder $purchaseOrder, array $drafts): int
    {
        if (empty($drafts)) {
            return 0;
        }

        $inserted = 0;
        $newDigitalProductIds = [];

        DB::transaction(function () use ($purchaseOrder, $drafts, &$inserted, &$newDigitalProductIds) {
            foreach ($drafts as $draft) {
                if ($this->isDuplicate($purchaseOrder, $draft)) {
                    continue;
                }

                Voucher::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'purchase_order_item_id' => $draft->purchaseOrderItemId,
                    'code' => $draft->code !== null ? $this->cipher->encryptCode($draft->code) : null,
                    'pin_code' => $draft->pin,
                    'serial_number' => $draft->serialNumber,
                    'stock_id' => $draft->stockId,
                    'expires_at' => $draft->expiresAt,
                    'status' => 'available',
                ]);

                $inserted++;
                $newDigitalProductIds[] = $draft->digitalProductId;
            }
        });

        if ($inserted > 0) {
            event(new NewVouchersAvailable(array_values(array_unique($newDigitalProductIds))));
        }

        return $inserted;
    }

    private function isDuplicate(PurchaseOrder $purchaseOrder, VoucherDraft $draft): bool
    {
        if (empty($draft->dedupeBy)) {
            return false;
        }

        return Voucher::where('purchase_order_id', $purchaseOrder->id)
            ->where($draft->dedupeBy)
            ->exists();
    }
}
