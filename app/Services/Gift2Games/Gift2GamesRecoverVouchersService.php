<?php

namespace App\Services\Gift2Games;

use App\Models\Voucher;
use App\Models\PurchaseOrder;
use App\Clients\Gift2Games\Order;
use App\Enums\PurchaseOrderStatus;
use Illuminate\Support\Facades\Log;
use App\Events\NewVouchersAvailable;
use App\Enums\PurchaseOrderSupplierStatus;
use App\Services\Voucher\VoucherCipherService;
use App\Services\PurchaseOrder\PurchaseOrderStatusService;

class Gift2GamesRecoverVouchersService
{
    public function __construct(
        private Order $orderClient,
        private VoucherCipherService $voucherCipherService,
        private PurchaseOrderStatusService $purchaseOrderStatusService,
    ) {}

    /**
     * Fetch all orders from Gift2Games, group by referenceNumber (purchase order number),
     * and store any missing voucher codes.
     *
     * @return array{
     *     total_references: int,
     *     processed_references: int,
     *     skipped_references: int,
     *     failed_references: int,
     *     total_vouchers_added: int,
     *     errors: array<int, array{reference: string, error: string}>
     * }
     */
    public function recoverMissingVouchers(): array
    {
        $summary = [
            'total_references' => 0,
            'processed_references' => 0,
            'skipped_references' => 0,
            'failed_references' => 0,
            'total_vouchers_added' => 0,
            'errors' => [],
        ];

        $response = $this->orderClient->getOrders();
        $orders = $response['data'] ?? [];

        if (empty($orders)) {
            return $summary;
        }

        // Group orders by referenceNumber (purchase order number)
        /** @var array<int, array<string, mixed>> $orders */
        $grouped = collect($orders)
            ->filter(fn (array $order) => ! empty($order['referenceNumber']))
            ->groupBy('referenceNumber');

        $summary['total_references'] = $grouped->count();

        foreach ($grouped as $referenceNumber => $g2gOrders) {
            try {
                $vouchersAdded = $this->processReference((string) $referenceNumber, $g2gOrders->all());

                if ($vouchersAdded === null) {
                    $summary['skipped_references']++;
                } else {
                    $summary['processed_references']++;
                    $summary['total_vouchers_added'] += $vouchersAdded;
                }
            } catch (\Exception $e) {
                $summary['failed_references']++;
                $summary['errors'][] = [
                    'reference' => (string) $referenceNumber,
                    'error' => $e->getMessage(),
                ];

                Log::error('Gift2Games recover vouchers: failed to process reference', [
                    'reference' => $referenceNumber,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $summary;
    }

    /**
     * Process a single referenceNumber group.
     * Returns number of vouchers added, or null if skipped.
     *
     * @param  array<int, array<string, mixed>>  $g2gOrders
     */
    private function processReference(string $referenceNumber, array $g2gOrders): ?int
    {
        $purchaseOrder = PurchaseOrder::with('items.digitalProduct')
            ->where('order_number', $referenceNumber)
            ->where('status', PurchaseOrderStatus::PROCESSING)
            ->first();

        if (! $purchaseOrder) {
            Log::warning('Gift2Games recover vouchers: purchase order not found', [
                'reference' => $referenceNumber,
            ]);

            return null;
        }

        // Only process completed G2G orders that have serial codes
        $completedOrders = collect($g2gOrders)->filter(
            fn (array $order) => ($order['orderStatus'] ?? '') === 'Completed'
                && ! empty($order['serialCode'])
        );

        if ($completedOrders->isEmpty()) {
            return null;
        }

        // Collect existing voucher codes (decrypted) to avoid duplicates
        $existingCodes = Voucher::where('purchase_order_id', $purchaseOrder->id)
            ->pluck('code')
            ->map(fn (string $code) => $this->voucherCipherService->isEncrypted($code)
                ? $this->voucherCipherService->decryptCode($code)
                : $code)
            ->flip(); // use as a set for O(1) lookup

        $vouchersAdded = 0;
        $newDigitalProductIds = [];

        foreach ($completedOrders as $order) {
            $serialCode = $order['serialCode'];
            $serialNumber = $order['serialNumber'] ?? null;
            $productName = $order['productName'] ?? null;

            // Skip if voucher already stored
            if ($existingCodes->has($serialCode)) {
                continue;
            }

            // Match purchase order item by digital product name / sku
            $purchaseOrderItem = $this->findMatchingItem($purchaseOrder, $productName);

            if (! $purchaseOrderItem) {
                Log::warning('Gift2Games recover vouchers: no matching purchase order item', [
                    'reference' => $referenceNumber,
                    'orderId' => $order['orderId'] ?? null,
                    'productName' => $productName,
                ]);

                continue;
            }

            $encryptedCode = $this->voucherCipherService->encryptCode($serialCode);

            Voucher::create([
                'purchase_order_id' => $purchaseOrder->id,
                'purchase_order_item_id' => $purchaseOrderItem->id,
                'code' => $encryptedCode,
                'serial_number' => $serialNumber,
                'status' => 'available',
            ]);

            // Add to set so we don't double-insert within the same run
            $existingCodes->put($serialCode, 1);
            $newDigitalProductIds[] = $purchaseOrderItem->digital_product_id;
            $vouchersAdded++;
        }

        $purchaseOrder->load('purchaseOrderSuppliers.supplier');

        $gift2GamesSupplier = $purchaseOrder->purchaseOrderSuppliers
            ->first(fn ($pos) => $pos->supplier?->slug === 'gift2games');

        if ($gift2GamesSupplier) {
            $supplierItems = $purchaseOrder->items->where('supplier_id', $gift2GamesSupplier->supplier_id);

            if ($supplierItems->isNotEmpty()) {
                $allItemsFulfilled = $supplierItems->every(
                    function (\App\Models\PurchaseOrderItem $item) use ($purchaseOrder) {
                        return Voucher::where('purchase_order_id', $purchaseOrder->id)
                            ->where('purchase_order_item_id', $item->id)
                            ->count() >= $item->quantity;
                    }
                );

                if ($allItemsFulfilled && $gift2GamesSupplier->status !== PurchaseOrderSupplierStatus::COMPLETED->value) {
                    $gift2GamesSupplier->update(['status' => PurchaseOrderSupplierStatus::COMPLETED]);
                    $this->purchaseOrderStatusService->updateStatus($purchaseOrder);
                }
            }
        }

        if ($vouchersAdded > 0) {
            $digitalProductIds = array_unique($newDigitalProductIds);
            event(new NewVouchersAvailable($digitalProductIds));
        }

        return $vouchersAdded;
    }

    /**
     * Find the purchase order item that matches the G2G product name.
     * Matches by digital product name (case-insensitive contains) or exact SKU.
     */
    private function findMatchingItem(PurchaseOrder $purchaseOrder, ?string $productName): ?\App\Models\PurchaseOrderItem
    {
        if (! $productName) {
            return $purchaseOrder->items->first();
        }

        $productNameLower = strtolower($productName);

        return $purchaseOrder->items->first(function (\App\Models\PurchaseOrderItem $item) use ($productNameLower) {
            $dpName = strtolower((string) ($item->digital_product_name ?? $item->digitalProduct?->name ?? ''));
            $dpSku = strtolower((string) ($item->digital_product_sku ?? $item->digitalProduct?->sku ?? ''));

            return str_contains($dpName, $productNameLower)
                || str_contains($productNameLower, $dpName)
                || ($dpSku && str_contains($productNameLower, $dpSku));
        });
    }
}
