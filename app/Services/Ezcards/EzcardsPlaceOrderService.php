<?php

namespace App\Services\Ezcards;

use App\Actions\Ezcards\PlaceOrder;
use Illuminate\Support\Facades\Log;

class EzcardsPlaceOrderService
{
    public function __construct(private PlaceOrder $ezcardsPlaceOrder) {}

    public function placeOrder(array $orderItems, string $orderNumber): array
    {
        $products = [];
        foreach ($orderItems as $item) {
            $products[] = [
                'sku' => $item['digital_product']->sku,
                'quantity' => $item['quantity'],
            ];
        }

        $requestData = [
            'clientOrderNumber' => $orderNumber,
            'products' => $products,
        ];

        $response = $this->ezcardsPlaceOrder->execute($requestData);

        $data = $response['data'] ?? [];

        Log::info('EzCards Order Placed', ['order_number' => $orderNumber, 'response' => $response]);

        return $data;
    }
}
