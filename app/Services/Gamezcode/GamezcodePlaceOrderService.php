<?php

namespace App\Services\Gamezcode;

use Illuminate\Support\Facades\Log;
use App\Actions\Gamezcode\PlaceOrderAction;

class GamezcodePlaceOrderService
{
    public function __construct(
        private PlaceOrderAction $placeOrderAction,
    ) {}

    /**
     * Place orders for all items with Gamezcode (Kalixo).
     *
     * Kalixo supports multi-product orders in a single request.
     * We build one combined payload and fire it in one call,
     * then map the returned PINs back to each purchase order item.
     *
     * @param  array  $orderItems  Array of PurchaseOrderItem models
     * @param  string  $orderNumber  The purchase order number used as externalOrderCode
     * @param  string  $currency  Currency code (e.g. 'GBP')
     * @return array ['transactionId' => string, 'vouchers' => array]
     */
    public function placeOrder(array $orderItems, string $orderNumber, string $currency): array
    {
        $orderProducts = [];
        $totalPrice = 0;

        foreach ($orderItems as $item) {
            $digitalProduct = $item->digitalProduct;
            $metadata = $digitalProduct->metadata ?? [];

            $unitPrice = (int) round($digitalProduct->cost_price);
            $quantity = (int) $item->quantity;
            $lineTotal = $unitPrice * $quantity;
            $totalPrice += $lineTotal;

            $orderProducts[] = [
                'productId' => (int) ($metadata['kalixo_id'] ?? 0),
                'sku' => (string) ($metadata['kalixo_sku'] ?? $digitalProduct->sku),
                'price' => $unitPrice,
                'currency' => strtoupper($currency),
                'quantity' => $quantity,
            ];
        }

        try {
            $response = $this->placeOrderAction->execute(
                $orderNumber,
                $totalPrice,
                strtoupper($currency),
                $orderProducts
            );
        } catch (\Exception $e) {
            Log::error('Gamezcode Place Order Error: '.$e->getMessage(), [
                'order_number' => $orderNumber,
            ]);

            throw $e;
        }

        $externalOrderId = $response['orderId'] ?? null;
        $returnedProducts = $response['products'] ?? [];

        // Build a keyed map of productId → returned product data for PIN extraction
        $productMap = [];
        foreach ($returnedProducts as $rp) {
            $productMap[(int) $rp['productId']] = $rp;
        }

        $vouchers = [];

        foreach ($orderItems as $item) {
            $digitalProduct = $item->digitalProduct;
            $metadata = $digitalProduct->metadata ?? [];
            $kalixoId = (int) ($metadata['kalixo_id'] ?? 0);

            $returnedProduct = $productMap[$kalixoId] ?? null;

            if ($returnedProduct === null) {
                Log::warning('Gamezcode: no returned product found for kalixo_id', [
                    'kalixo_id' => $kalixoId,
                    'order_number' => $orderNumber,
                ]);

                continue;
            }

            $pins = $returnedProduct['pin'] ?? [];

            foreach ($pins as $pinEntry) {
                $vouchers[] = [
                    'code' => $pinEntry['PIN'] ?? null,
                    'pin' => $pinEntry['PIN'] ?? null,
                    'serial' => null,
                    'externalOrderId' => $externalOrderId,
                    'digital_product_id' => $item->digital_product_id,
                    'purchase_order_item_id' => $item->id,
                ];
            }
        }

        Log::info('Gamezcode order placed successfully', [
            'order_number' => $orderNumber,
            'external_order_id' => $externalOrderId,
            'vouchers_count' => count($vouchers),
        ]);

        return [
            'transactionId' => $externalOrderId,
            'vouchers' => $vouchers,
        ];
    }
}
