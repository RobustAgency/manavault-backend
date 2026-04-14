<?php

namespace App\Services\Tikkery;

use Illuminate\Support\Facades\Log;
use App\Actions\Tikkery\CreateOrder;

class TikkeryPlaceOrderService
{
    public function __construct(private CreateOrder $createOrder) {}

    /**
     * Place an order with Tikkery for the given purchase order items.
     */
    public function placeOrder(array $orderItems, string $orderNumber): array
    {
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
     * by matching codes back to purchase order items by SKU position.
     *
     * @param  array<int, array<string, mixed>>  $codes
     * @param  array  $orderItems  PurchaseOrderItem models
     * @return array<int, array<string, mixed>>
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
}
