<?php

namespace App\Services\Ezcards;

use Illuminate\Support\Facades\Log;
use App\Actions\Ezcards\PlaceOrderAction;

class EzcardsPlaceOrderService
{
    public function __construct(private PlaceOrderAction $ezcardsPlaceOrder) {}

    public function placeOrder(array $orderItems, string $orderNumber, string $currency): array
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
            'payWithCurrency' => strtoupper($currency),
        ];

        $response = $this->ezcardsPlaceOrder->execute($requestData);

        $data = $response['data'] ?? [];

        Log::info('EzCards Order Placed', ['order_number' => $orderNumber, 'response' => $response]);

        return $data;
    }
}
