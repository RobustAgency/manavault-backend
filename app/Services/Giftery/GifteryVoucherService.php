<?php

namespace App\Services\Giftery;

use App\Models\Voucher;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Log;
use App\Events\NewVouchersAvailable;
use App\Services\Voucher\VoucherCipherService;

class GifteryVoucherService
{
    public function __construct(private VoucherCipherService $voucherCipherService) {}

    /**
     * Process and store vouchers from Giftery order response.
     */
    public function storeVouchers(PurchaseOrder $purchaseOrder, array $gifteryResponse): void
    {
        logger()->info('Storing Giftery vouchers', [
            'purchase_order_id' => $purchaseOrder->id,
            'gifteryResponse' => $gifteryResponse,
        ]);
        /** @var array<int, array<string, mixed>> $vouchers */
        $vouchers = $gifteryResponse['vouchers'];

        if (empty($vouchers)) {
            return;
        }

        try {
            // Store immediate vouchers (codes available now)
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
            Log::error('Failed to store Giftery vouchers', [
                'purchase_order_id' => $purchaseOrder->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        // Fire event only if we have immediate vouchers ready for allocation
        $digitalProductIds = collect($vouchers)
            ->pluck('digital_product_id')
            ->unique()
            ->values()
            ->all();

        event(new NewVouchersAvailable($digitalProductIds, $purchaseOrder->id, $purchaseOrder->sale_order_id));
    }
}
