<?php

namespace App\Services\Gift2Games;

use Illuminate\Support\Facades\Log;
use App\Actions\Gift2Games\CreateOrder;
use App\Actions\Gift2Games\CheckBalance;

class Gift2GamesPlaceOrderService
{
    public function __construct(
        private CreateOrder $gift2GamesPlaceOrder,
        private CheckBalance $checkBalance,
    ) {}

    public function placeOrder(array $orderItems, string $orderNumber, string $supplierSlug = 'gift2games'): array
    {
        $totalPrice = 0;
        foreach ($orderItems as $item) {
            $totalPrice += $item['subtotal'];
        }
        $hasSufficientBalance = $this->hasSufficientBalance($totalPrice, $supplierSlug);
        if (! $hasSufficientBalance) {
            Log::error("Insufficient balance for Gift2Games order. Required: {$totalPrice}");

            throw new \Exception('Insufficient balance in Gift2Games account to place the order.');
        }
        $vouchers = [];
        foreach ($orderItems as $item) {
            for ($i = 0; $i < $item['quantity']; $i++) {
                $data = [
                    'productId' => (int) $item['digital_product']->sku,
                    'referenceNumber' => $orderNumber,
                ];
                try {
                    $response = $this->gift2GamesPlaceOrder->execute($data, $supplierSlug);
                } catch (\Exception $e) {
                    Log::error('Gift2Games Place Order Error: '.$e->getMessage());

                    continue;
                }
                $voucherData = $response['data'];
                $voucherData['digital_product_id'] = $item['digital_product_id'];
                $vouchers[] = $voucherData;
            }
        }

        return $vouchers;
    }

    /**
     * Check if the sufficient balance is available in the wallet.
     */
    private function hasSufficientBalance(float $totalPrice, string $supplierSlug = 'gift2games'): bool
    {
        $response = $this->checkBalance->execute($supplierSlug);

        $availableBalance = (float) ($response['data']['userBalance'] ?? 0);

        return $availableBalance >= $totalPrice;
    }
}
