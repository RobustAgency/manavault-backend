<?php

namespace App\Integrations\Gift2Games;

use App\Models\Voucher;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\DTOs\Supplier\VoucherData;
use Illuminate\Support\Facades\Log;
use App\Models\PurchaseOrderSupplier;
use App\Actions\Gift2Games\CreateOrder;
use App\Actions\Gift2Games\CheckBalance;
use App\DTOs\Supplier\VoucherFetchResult;
use App\DTOs\Supplier\SupplierOrderResult;
use App\Contracts\SupplierIntegrationContract;
use App\Services\Gift2Games\SyncDigitalProducts;

class Gift2GamesIntegration implements SupplierIntegrationContract
{
    public function __construct(
        private readonly CreateOrder $createOrder,
        private readonly CheckBalance $checkBalance,
        private readonly SyncDigitalProducts $syncDigitalProducts,
    ) {}

    /**
     * Place individual voucher orders per item quantity and return all vouchers.
     * Idempotent: skips slots already covered by existing Voucher records.
     */
    public function placeOrder(
        array $items,
        string $orderNumber,
        PurchaseOrder $po,
        Supplier $supplier,
    ): SupplierOrderResult {
        $supplierSlug = $supplier->slug;
        $remainingCost = $this->calculateRemainingCost($items);

        if ($remainingCost > 0 && ! $this->hasSufficientBalance($remainingCost, $supplierSlug)) {
            throw new \RuntimeException(
                "Insufficient balance for Gift2Games order. Required: {$remainingCost}"
            );
        }

        $vouchers = [];

        foreach ($items as $item) {
            $existingCount = Voucher::where('purchase_order_item_id', $item->id)->count();

            for ($i = $existingCount; $i < $item->quantity; $i++) {
                try {
                    $response = $this->createOrder->execute([
                        'productId' => (int) $item->digitalProduct->sku,
                        'referenceNumber' => $orderNumber,
                    ], $supplierSlug);
                } catch (\Exception $e) {
                    Log::error('Gift2Games place order error', [
                        'item_id' => $item->id,
                        'supplier_slug' => $supplierSlug,
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }

                $voucherData = $response['data'];

                $vouchers[] = new VoucherData(
                    code: $voucherData['serialCode'] ?? '',
                    purchaseOrderItemId: $item->id,
                    serialNumber: $voucherData['serialNumber'] ?? null,
                    pinCode: null,
                );
            }
        }

        return new SupplierOrderResult(
            transactionId: null,
            isComplete: true,
            vouchers: $vouchers,
        );
    }

    public function isVoucherDeliveryImmediate(): bool
    {
        return true;
    }

    /**
     * G2G delivers vouchers synchronously — this method is provided for interface
     * completeness but should never be invoked during normal order flow.
     */
    public function fetchPendingVouchers(
        PurchaseOrder $po,
        PurchaseOrderSupplier $poSupplier,
    ): VoucherFetchResult {
        return new VoucherFetchResult(vouchers: [], isPending: false);
    }

    public function syncProducts(): void
    {
        $this->syncDigitalProducts->processSyncAllProducts();
    }

    private function calculateRemainingCost(array $items): float
    {
        $remaining = 0.0;

        foreach ($items as $item) {
            $existingCount = Voucher::where('purchase_order_item_id', $item->id)->count();
            $remainingQty = max(0, $item->quantity - $existingCount);
            $unitCost = $item->quantity > 0 ? (float) $item->subtotal / $item->quantity : 0.0;
            $remaining += $unitCost * $remainingQty;
        }

        return $remaining;
    }

    private function hasSufficientBalance(float $total, string $supplierSlug): bool
    {
        $response = $this->checkBalance->execute($supplierSlug);

        return (float) ($response['data']['userBalance'] ?? 0) >= $total;
    }
}
