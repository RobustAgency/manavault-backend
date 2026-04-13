<?php

namespace App\Services\Giftery;

use Illuminate\Support\Facades\Log;
use App\Actions\Giftery\PlaceOrderAction;
use App\Actions\Giftery\CheckProductStockAction;
use App\Actions\Giftery\GetAccountBalanceAction;

class GifteryPlaceOrderService
{
    public function __construct(
        private PlaceOrderAction $placeOrderAction,
        private CheckProductStockAction $checkProductStockAction,
        private GetAccountBalanceAction $getAccountBalanceAction,
    ) {}

    /**
     * Place orders for all items with Giftery.
     *
     * @param  array  $orderItems  Array of PurchaseOrderItem models
     * @param  string  $orderNumber  The purchase order number for reference
     */
    public function placeOrder(array $orderItems, string $orderNumber): array
    {
        // Check account balance before processing orders
        $this->checkAccountBalance($orderItems, $orderNumber);

        // Check stock availability before processing orders
        $stockCheck = $this->checkProductStockAction->execute($orderItems);

        if (! $stockCheck['inStock']) {
            Log::error('Giftery stock check failed', [
                'order_number' => $orderNumber,
                'unavailable_items' => $stockCheck['unavailableItems'],
            ]);

            throw new \RuntimeException('One or more items are out of stock with Giftery. See logs for details.');
        }

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

    /**
     * Check if the account has sufficient available balance for the order.
     *
     * Uses the available balance (not credit limit) to determine if the order can proceed.
     *
     * @return array Account balance information
     *
     * @throws \RuntimeException If balance is insufficient or if fetching balance fails
     */
    private function checkAccountBalance(array $orderItems, string $orderNumber): array
    {
        try {
            $account = $this->getAccountBalanceAction->execute();
        } catch (\Exception $e) {
            Log::error('Giftery get account balance error: '.$e->getMessage(), [
                'order_number' => $orderNumber,
            ]);

            throw new \RuntimeException('Failed to check account balance with Giftery. See logs for details.');
        }

        // Calculate total order amount
        $totalAmount = collect($orderItems)->sum('subtotal');

        $availableBalance = $account['available'] ?? 0;

        if ($totalAmount > $availableBalance) {
            Log::error('Giftery insufficient balance', [
                'order_number' => $orderNumber,
                'required_amount' => $totalAmount,
                'available_balance' => $availableBalance,
            ]);

            throw new \RuntimeException(
                "Insufficient balance with Giftery. Required: {$totalAmount}, Available: {$availableBalance}"
            );
        }

        Log::info('Giftery balance check passed', [
            'order_number' => $orderNumber,
            'required_amount' => $totalAmount,
            'available_balance' => $availableBalance,
        ]);

        return $account;
    }
}
