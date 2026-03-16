<?php

namespace App\Services\Gift2Games;

use App\Models\Voucher;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Log;
use App\Events\NewVouchersAvailable;
use App\Services\Voucher\VoucherCipherService;

class Gift2GamesVoucherService
{
    public function __construct(
        private VoucherCipherService $voucherCipherService
    ) {}

    /**
     * Process and store vouchers from Gift2Games order response.
     *
     * @param  array<int, array{purchase_order_item_id: int, digital_product_id: int, serialCode?: string, serialNumber?: string}>  $voucherCodesResponse
     */
    public function storeVouchers(PurchaseOrder $purchaseOrder, array $voucherCodesResponse): void
    {
        if (empty($voucherCodesResponse)) {
            return;
        }

        try {
            foreach ($voucherCodesResponse as $voucherData) {
                $voucherCode = $voucherData['serialCode'] ?? null;

                if ($voucherCode) {
                    $voucherCode = $this->voucherCipherService->encryptCode($voucherCode);
                }

                Voucher::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'purchase_order_item_id' => $voucherData['purchase_order_item_id'],
                    'code' => $voucherCode,
                    'serial_number' => $voucherData['serialNumber'] ?? null,
                    'status' => 'available',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to store Gift2Games vouchers', [
                'purchase_order_id' => $purchaseOrder->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $digitalProductIds = collect($voucherCodesResponse)->pluck('digital_product_id')->unique()->values()->all();
        event(new NewVouchersAvailable($digitalProductIds));
    }
}
