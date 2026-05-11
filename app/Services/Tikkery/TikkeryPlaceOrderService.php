<?php

namespace App\Services\Tikkery;

use App\Actions\Tikkery\GetStock;
use App\Actions\Tikkery\GetBalance;
use Illuminate\Support\Facades\Log;
use App\Actions\Tikkery\CreateOrder;

class TikkeryPlaceOrderService
{
    public function __construct(
        private CreateOrder $createOrder,
        private GetBalance $getBalance,
        private GetStock $getStock,
    ) {}

    /**
     * Place an order with Tikkery for the given purchase order items.
     */
    public function placeOrder(array $orderItems, string $orderNumber): array
    {
        $this->checkAccountBalance($orderItems, $orderNumber);

        $this->checkStock($orderItems, $orderNumber);

        $lineItems = [];

        foreach ($orderItems as $item) {
            $lineItems[] = [
                'sku' => $item->digitalProduct->sku,
                'qty' => (string) $item->quantity,
                'price' => (float) $item->unit_cost,
            ];
        }

        $requestData = [
            'lineItems' => $lineItems,
            'customerReference' => $orderNumber,
        ];

        $response = $this->createOrder->execute($requestData);

        $tikkeryOrderNumber = $response['order']['number'] ?? null;
        $isCompleted = (bool) ($response['order']['isCompleted'] ?? false);
        $codes = $response['codes'] ?? [];

        Log::info('Tikkery order placed', [
            'order_number' => $orderNumber,
            'tikkery_order_number' => $tikkeryOrderNumber,
            'is_completed' => $isCompleted,
            'codes_count' => count($codes),
        ]);

        $enrichedCodes = $this->enrichCodes($codes, $orderItems);

        return [
            'transactionId' => $tikkeryOrderNumber,
            'isCompleted' => $isCompleted,
            'codes' => $enrichedCodes,
        ];
    }

    /**
     * Enrich raw Tikkery codes with purchase_order_item_id and digital_product_id
     */
    public function enrichCodes(array $codes, array $orderItems): array
    {
        $skuToItems = [];
        foreach ($orderItems as $item) {
            $sku = $item->digitalProduct->sku;
            $skuToItems[$sku][] = $item;
        }

        $enrichedCodes = [];
        $skuCursors = [];

        foreach ($codes as $code) {
            $sku = $code['productSku'];
            $cursor = $skuCursors[$sku] ?? 0;
            $items = $skuToItems[$sku] ?? [];

            $matchedItem = null;
            $consumed = 0;
            foreach ($items as $item) {
                $consumed += $item->quantity;
                if ($cursor < $consumed) {
                    $matchedItem = $item;
                    break;
                }
            }

            $skuCursors[$sku] = $cursor + 1;

            $enrichedCodes[] = array_merge($code, [
                'digital_product_id' => $matchedItem?->digital_product_id,
                'purchase_order_item_id' => $matchedItem?->id,
            ]);
        }

        return $enrichedCodes;
    }

    /**
     * Check if the Tikkery wallet has sufficient balance to cover the total order cost.
     *
     * @throws \RuntimeException If balance is insufficient or cannot be fetched
     */
    private function checkAccountBalance(array $orderItems, string $orderNumber): void
    {
        try {
            $response = $this->getBalance->execute(now()->toIso8601String());
        } catch (\Exception $e) {
            Log::error('Tikkery get balance error: '.$e->getMessage(), [
                'order_number' => $orderNumber,
            ]);

            throw new \RuntimeException('Failed to check account balance with Tikkery. See logs for details.');
        }

        $availableBalance = (float) ($response['balance'] ?? 0);
        $totalAmount = (float) collect($orderItems)->sum('subtotal');

        if ($totalAmount > $availableBalance) {
            Log::error('Tikkery insufficient balance', [
                'order_number' => $orderNumber,
                'required_amount' => $totalAmount,
                'available_balance' => $availableBalance,
            ]);

            throw new \RuntimeException(
                "Insufficient balance with Tikkery. Required: {$totalAmount}, Available: {$availableBalance}"
            );
        }
    }

    /**
     * Check that all order item SKUs have sufficient stock on Tikkery.
     *
     * @throws \RuntimeException If any SKU is out of stock or the check fails
     */
    private function checkStock(array $orderItems, string $orderNumber): void
    {
        $requiredQty = [];
        foreach ($orderItems as $item) {
            $sku = $item->digitalProduct->sku;
            $requiredQty[$sku] = ($requiredQty[$sku] ?? 0) + (int) $item->quantity;
        }

        $skus = array_keys($requiredQty);

        try {
            $response = $this->getStock->execute($skus);
        } catch (\Exception $e) {
            Log::error('Tikkery get stock error: '.$e->getMessage(), [
                'order_number' => $orderNumber,
            ]);

            throw new \RuntimeException('Failed to check stock availability with Tikkery. See logs for details.');
        }

        $availableStock = [];
        foreach ($response['stock'] ?? [] as $entry) {
            $sku = $entry['sku'];
            $availableStock[$sku] = ($availableStock[$sku] ?? 0) + (int) $entry['stock'];
        }

        $unavailableSkus = [];
        foreach ($requiredQty as $sku => $needed) {
            $available = $availableStock[$sku] ?? 0;
            if ($needed > $available) {
                $unavailableSkus[] = [
                    'sku' => $sku,
                    'required' => $needed,
                    'available' => $available,
                ];
            }
        }

        if (! empty($unavailableSkus)) {
            Log::error('Tikkery stock check failed', [
                'order_number' => $orderNumber,
                'unavailable_skus' => $unavailableSkus,
            ]);

            throw new \RuntimeException('One or more items are out of stock with Tikkery. See logs for details.');
        }
    }
}
