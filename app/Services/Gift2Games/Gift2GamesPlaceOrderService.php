<?php

namespace App\Services\Gift2Games;

use App\Models\Voucher;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Log;
use App\Events\NewVouchersAvailable;
use App\Actions\Gift2Games\CreateOrder;
use App\Actions\Gift2Games\CheckBalance;
use App\Services\Voucher\VoucherCipherService;

class Gift2GamesPlaceOrderService
{
    public function __construct(
        private CreateOrder $gift2GamesPlaceOrder,
        private CheckBalance $checkBalance,
        private VoucherCipherService $voucherCipherService,
    ) {}

    public function placeOrder(array $orderItems, string $orderNumber, string $supplierSlug, PurchaseOrder $purchaseOrder): void
    {
        $remainingCost = $this->calculateRemainingCost($orderItems);

        if ($remainingCost > 0 && ! $this->hasSufficientBalance($remainingCost, $supplierSlug)) {
            Log::error("Insufficient balance for Gift2Games order. Required: {$remainingCost}");

            throw new \Exception('Insufficient balance in Gift2Games account to place the order.');
        }

        $newDigitalProductIds = [];

        foreach ($orderItems as $item) {
            $existingCount = Voucher::where('purchase_order_item_id', $item->id)->count();

            for ($i = $existingCount; $i < $item->quantity; $i++) {
                $data = [
                    'productId' => (int) $item->digitalProduct->sku,
                    'referenceNumber' => $orderNumber,
                ];
                try {
                    $response = $this->gift2GamesPlaceOrder->execute($data, $supplierSlug);
                } catch (\Exception $e) {
                    Log::error('Gift2Games Place Order Error: '.$e->getMessage());

                    continue;
                }

                $voucherData = $response['data'];
                $voucherCode = isset($voucherData['serialCode'])
                    ? $this->voucherCipherService->encryptCode($voucherData['serialCode'])
                    : null;

                Voucher::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'purchase_order_item_id' => $item->id,
                    'code' => $voucherCode,
                    'serial_number' => $voucherData['serialNumber'] ?? null,
                    'status' => 'available',
                ]);

                $newDigitalProductIds[] = $item->digital_product_id;
            }
        }

        if (! empty($newDigitalProductIds)) {
            event(new NewVouchersAvailable(array_unique($newDigitalProductIds)));
        }
    }

    /**
     * Calculate the cost of units not yet fulfilled (skips already-stored vouchers).
     * Used to avoid false balance failures on job retries.
     */
    private function calculateRemainingCost(array $orderItems): float
    {
        $remaining = 0.0;

        foreach ($orderItems as $item) {
            $existingCount = Voucher::where('purchase_order_item_id', $item->id)->count();
            $remainingQty = max(0, $item->quantity - $existingCount);
            $unitCost = $item->quantity > 0 ? (float) $item->subtotal / $item->quantity : 0.0;
            $remaining += $unitCost * $remainingQty;
        }

        return $remaining;
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
