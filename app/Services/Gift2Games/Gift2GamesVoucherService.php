<?php

namespace App\Services\Gift2Games;

use App\Models\Voucher;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Log;
use App\Services\VoucherCipherService;
use App\Services\PurchaseOrder\PurchaseOrderStatusService;

class Gift2GamesVoucherService
{
    public function __construct(
        private VoucherCipherService $voucherCipherService,
        private PurchaseOrderStatusService $purchaseOrderStatusService
    ) {}

    /**
     * Process and store vouchers from Gift2Games order response.
     *
     * @return array Processing result
     */
    public function storeVouchers(PurchaseOrder $purchaseOrder, array $voucherCodesResponse): array
    {
        $result = [
            'vouchers_added' => 0,
            'status_updated' => false,
        ];

        if (empty($voucherCodesResponse)) {
            return $result;
        }

        // Load purchase order items with digital products
        $purchaseOrder->load('items.digitalProduct');

        $voucherCountByProduct = [];
        $vouchersAdded = 0;

        try {
            foreach ($voucherCodesResponse as $voucherData) {
                $digitalProductId = $voucherData['digital_product_id'] ?? null;

                if (! $digitalProductId) {
                    Log::warning('Gift2Games voucher missing digital_product_id', [
                        'voucher' => $voucherData,
                        'purchase_order_id' => $purchaseOrder->id,
                    ]);

                    continue;
                }

                // Initialize counter for this product if not exists
                if (! isset($voucherCountByProduct[$digitalProductId])) {
                    $voucherCountByProduct[$digitalProductId] = 0;
                }

                // Find the purchase order item for this digital product
                $purchaseOrderItem = $purchaseOrder->items->first(function ($item) use ($digitalProductId) {
                    return $item->digital_product_id == $digitalProductId;
                });

                if (! $purchaseOrderItem) {
                    Log::warning('No purchase order item found for Gift2Games voucher', [
                        'digital_product_id' => $digitalProductId,
                        'voucher' => $voucherData,
                        'purchase_order_id' => $purchaseOrder->id,
                    ]);

                    continue;
                }

                // Increment the counter
                $voucherCountByProduct[$digitalProductId]++;

                // Verify we're not exceeding the ordered quantity
                if ($voucherCountByProduct[$digitalProductId] > $purchaseOrderItem->quantity) {
                    Log::warning('Received more vouchers than ordered quantity', [
                        'digital_product_id' => $digitalProductId,
                        'ordered_quantity' => $purchaseOrderItem->quantity,
                        'received_count' => $voucherCountByProduct[$digitalProductId],
                        'purchase_order_id' => $purchaseOrder->id,
                    ]);
                }

                $voucherCode = $voucherData['serialCode'] ?? null;

                if ($voucherCode) {
                    $voucherCode = $this->voucherCipherService->encryptCode($voucherCode);
                }

                // Create the voucher
                Voucher::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'purchase_order_item_id' => $purchaseOrderItem->id,
                    'code' => $voucherCode,
                    'serial_number' => $voucherData['serialNumber'] ?? null,
                    'status' => 'available',
                ]);

                $vouchersAdded++;
            }
            $this->purchaseOrderStatusService->updateStatus($purchaseOrder);

            $result['vouchers_added'] = $vouchersAdded;
        } catch (\Exception $e) {
            Log::error('Failed to store Gift2Games vouchers', [
                'purchase_order_id' => $purchaseOrder->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $result;
    }
}
