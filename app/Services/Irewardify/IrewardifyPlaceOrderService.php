<?php

namespace App\Services\Irewardify;

use Illuminate\Support\Facades\Log;
use App\Actions\Irewardify\Checkout;
use App\Actions\Irewardify\GetWalletBalance;

class IrewardifyPlaceOrderService
{
    public function __construct(
        private Checkout $checkout,
        private GetWalletBalance $getWalletBalance,
    ) {}

    public function placeOrder(array $orderItems, string $orderNumber): array
    {
        $totalPrice = 0;
        foreach ($orderItems as $item) {
            $totalPrice += $item->subtotal;
        }

        $this->ensureSufficientBalance($totalPrice);

        $items = [];
        foreach ($orderItems as $item) {
            $metadata = $item->digitalProduct->metadata ?? [];
            $variant = $metadata['variant'] ?? [];

            $itemId = $variant['item_id'] ?? null;
            $productId = $variant['product_id'] ?? null;

            if (! $itemId || ! $productId) {
                Log::error("Irewardify order: missing item_id or product_id in metadata for digital product ID {$item->digital_product_id} (SKU: {$item->digital_product_sku}).");

                throw new \RuntimeException("Missing item_id or product_id in metadata for digital product SKU: {$item->digital_product_sku}.");
            }

            $items[] = [
                'item_id' => $itemId,
                'productType' => 'Digital',
                'product_id' => $productId,
                'quantity' => $item->quantity,
            ];
        }

        $payload = [
            'externalOrderId' => $orderNumber,
            'items' => $items,
        ];

        Log::info('Irewardify placing order', ['order_number' => $orderNumber, 'payload' => $payload]);

        $response = $this->checkout->execute($payload);

        $data = $response['data'] ?? [];
        $orderId = $data['orderId'] ?? null;

        Log::info('Irewardify order placed', [
            'order_number' => $orderNumber,
            'order_id' => $orderId,
        ]);

        return [
            'transactionId' => $orderId,
            'externalOrderId' => $data['externalOrderId'] ?? $orderNumber,
            'orderId' => $orderId,
        ];
    }

    private function ensureSufficientBalance(float $totalPrice): void
    {
        $response = $this->getWalletBalance->execute();
        $availableBalance = (float) ($response['data']['balance'] ?? $response['balance'] ?? 0);

        if ($availableBalance < $totalPrice) {
            Log::error("Irewardify order: insufficient balance. Required: {$totalPrice}, Available: {$availableBalance}");

            throw new \RuntimeException('Insufficient balance in Irewardify account to place the order.');
        }
    }
}
