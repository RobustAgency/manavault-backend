<?php

namespace App\Services\Gift2Games;

use Illuminate\Support\Facades\Log;
use App\Actions\Gift2Games\CreateOrder;

class Gift2GamesPlaceOrderService
{
    public function __construct(private CreateOrder $gift2GamesPlaceOrder) {}

    public function placeOrder(array $orderItems, string $orderNumber): array
    {
        $vouchers = [];
        foreach ($orderItems as $item) {
            for ($i = 0; $i < $item['quantity']; $i++) {
                $data = [
                    'productId' => (int) $item['digital_product']->sku,
                    'referenceNumber' => $orderNumber,
                ];
                try {
                    $response = $this->gift2GamesPlaceOrder->execute($data);
                } catch (\Exception $e) {
                    Log::error('Gift2Games Place Order Error: '.$e->getMessage());

                    continue;
                }

                $vouchers[] = $response;
            }
        }

        return $vouchers;
    }
}
