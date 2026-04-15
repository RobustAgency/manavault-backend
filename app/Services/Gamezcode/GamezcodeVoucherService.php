<?php

namespace App\Services\Gamezcode;

use App\Models\Voucher;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Log;
use App\Events\NewVouchersAvailable;
use App\Services\Voucher\VoucherCipherService;

class GamezcodeVoucherService
{
    public function __construct(private VoucherCipherService $voucherCipherService) {}

    /**
     * Process and store vouchers from a Gamezcode order response.
     */
    public function storeVouchers(PurchaseOrder $purchaseOrder, array $gamezCodeResponse): void
    {
        logger()->info('Storing Gamezcode vouchers', [
            'purchase_order_id' => $purchaseOrder->id,
            'gamezCodeResponse' => $gamezCodeResponse,
        ]);

        /** @var array<int, array<string, mixed>> $vouchers */
        $vouchers = $gamezCodeResponse['vouchers'];

        if (empty($vouchers)) {
            return;
        }

        try {
            foreach ($vouchers as $voucherData) {
                Voucher::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'purchase_order_item_id' => $voucherData['purchase_order_item_id'],
                    'code' => $this->voucherCipherService->encryptCode($voucherData['pin'] ?? null),
                    'serial_number' => $voucherData['serial'] ?? null,
                    'pin_code' => $voucherData['pin'] ?? null,
                    'status' => 'available',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to store Gamezcode vouchers', [
                'purchase_order_id' => $purchaseOrder->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $digitalProductIds = collect($vouchers)
            ->pluck('digital_product_id')
            ->unique()
            ->values()
            ->all();

        event(new NewVouchersAvailable($digitalProductIds));
    }
}
