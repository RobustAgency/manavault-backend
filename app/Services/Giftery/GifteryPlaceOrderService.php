<?php

namespace App\Services\Giftery;

use Illuminate\Support\Facades\Log;
use App\Actions\Giftery\PlaceOrderAction;

class GifteryPlaceOrderService
{
    public function __construct(
        private PlaceOrderAction $placeOrderAction,
    ) {}

    /**
     * Place orders for all items with Giftery.
     *
     * Returns an array with two keys:
     * - 'vouchers': items where codes were returned immediately (store right away)
     * - 'pending':  items where codes are NOT yet available (need polling via getOperation)
     *
     * @param  array  $orderItems  Array of PurchaseOrderItem models
     * @param  string  $orderNumber  The purchase order number for reference
     */
    public function placeOrder(array $orderItems, string $orderNumber): array
    {
        $vouchers = [];
        $transactionUUID = null;

        foreach ($orderItems as $item) {
            $payload = [
                'itemId' => (int) $item->digitalProduct->sku,
                'fields' => $this->buildFields($item),
                'clientTime' => now()->toIso8601String(),
                'referenceId' => $orderNumber.'-'.$item->id,
            ];

            try {
                $response = $this->placeOrderAction->execute($payload);
                logger()->info('Giftery reserve response', [
                    'reserveResponse' => $response,
                ]);
            } catch (\Exception $e) {
                Log::error('Giftery Place Order Error: '.$e->getMessage(), [
                    'order_number' => $orderNumber,
                    'sku' => $item->digitalProduct->sku,
                ]);

                continue;
            }

            logger()->info('Giftery confirm response inside service', [
                'confirmResponse' => $response,
            ]);

            $transactionUUID = $response['transactionUUID'] ?? null;
            $confirmResponse = $response['confirmResponse'] ?? [];
            $vouchers_list = $confirmResponse['vouchers'] ?? [];

            if (! empty($vouchers_list)) {
                foreach ($vouchers_list as $voucher) {
                    $voucherData = $this->extractVoucherData($voucher, $transactionUUID);
                    $voucherData['digital_product_id'] = $item->digital_product_id;
                    $voucherData['purchase_order_item_id'] = $item->id;
                    $vouchers[] = $voucherData;
                }
            }
        }

        return [
            'transactionId' => $transactionUUID,
            'vouchers' => $vouchers,
        ];
    }

    /**
     * Build the `fields` array for the Giftery reserve payload.
     *
     * Giftery items may require specific fields (e.g., email for digital delivery).
     * The required fields are stored in the digital product's metadata during sync.
     */
    private function buildFields(mixed $item): array
    {
        return [
            ['key' => 'quantity', 'value' => $item->quantity],
        ];
    }

    /**
     * Extract voucher data from a single Giftery voucher object.
     */
    private function extractVoucherData(array $voucher, ?string $transactionUUID = null): array
    {
        return [
            'code' => $voucher['serialNumber'] ?? null,
            'pin' => $voucher['pin'] ?? null,
            'serial' => $voucher['serialNumber'] ?? null,
            'expiryDate' => $voucher['expiryDate'] ?? null,
            'transactionUUID' => $transactionUUID,
        ];
    }
}
