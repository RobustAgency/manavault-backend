<?php

namespace App\Actions\Giftery;

use App\Clients\Giftery\Client;

class CheckProductStockAction
{
    public function __construct(private Client $gifteryClient) {}

    /**
     * Check stock availability for all items.
     *
     * @param  array  $orderItems  Array of PurchaseOrderItem models
     * @return array Array with 'inStock' (bool) and 'unavailableItems' (array of item details)
     */
    public function execute(array $orderItems): array
    {
        $unavailableItems = [];
        $allInStock = true;

        foreach ($orderItems as $item) {
            try {
                $productDetails = $this->gifteryClient->getProductItemDetails((int) $item->digitalProduct->sku);

                if (($productDetails['statusCode'] ?? -1) !== 0) {
                    $allInStock = false;
                    $unavailableItems[] = [
                        'item_id' => $item->id,
                        'sku' => $item->digitalProduct->sku,
                        'reason' => $productDetails['message'] ?? 'Product not found',
                    ];

                    continue;
                }

                $product = $productDetails['data'] ?? null;
                $requestedQuantity = $item->quantity;
                $availableQuantity = $product['inStock'] ?? 0;

                if ($availableQuantity < $requestedQuantity) {
                    $allInStock = false;
                    $unavailableItems[] = [
                        'item_id' => $item->id,
                        'sku' => $item->digitalProduct->sku,
                        'requested' => $requestedQuantity,
                        'available' => $availableQuantity,
                        'reason' => "Insufficient stock. Requested: {$requestedQuantity}, Available: {$availableQuantity}",
                    ];
                }
            } catch (\Exception $e) {
                $allInStock = false;
                $unavailableItems[] = [
                    'item_id' => $item->id,
                    'sku' => $item->digitalProduct->sku,
                    'reason' => 'Error checking stock: '.$e->getMessage(),
                ];
            }
        }

        return [
            'inStock' => $allInStock,
            'unavailableItems' => $unavailableItems,
        ];
    }
}
