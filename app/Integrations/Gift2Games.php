<?php

namespace App\Integrations;

use App\Models\Voucher;
use App\Models\Gift2GamesOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\Log;
use App\Enums\Gift2GamesOrderStatus;
use App\Actions\Gift2Games\GetProduct;
use App\Enums\PurchaseOrderItemStatus;
use App\Actions\Gift2Games\CreateOrder;
use App\Contracts\SupplierIntegrationContract;
use App\Services\Voucher\VoucherCipherService;
use App\Services\Gift2Games\SyncDigitalProducts;

class Gift2Games implements SupplierIntegrationContract
{
    public function __construct(
        private readonly string $supplierSlug,
        private readonly CreateOrder $createOrder,
        private readonly GetProduct $getProduct,
        private readonly SyncDigitalProducts $syncDigitalProducts,
        private readonly VoucherCipherService $voucherCipherService,
    ) {}

    public function placeOrder(PurchaseOrderItem $item): void
    {
        if ($item->transaction_id) {
            Log::warning('Gift2Games placeOrder called for PurchaseOrderItem with existing transaction_id', [
                'supplier' => $this->supplierSlug,
                'purchase_order_item_id' => $item->id,
                'transaction_id' => $item->transaction_id,
            ]);

            return;
        }
        $batchNumber = 'batch_'.$item->id;

        Log::info('Gift2Games placeOrder: creating order', [
            'supplier' => $this->supplierSlug,
            'purchase_order_item_id' => $item->id,
            'sku' => $item->digitalProduct->sku ?? null,
            'quantity' => $item->quantity,
            'batch_number' => $batchNumber,
        ]);

        for ($i = 0; $i < $item->quantity; $i++) {
            Gift2GamesOrder::create([
                'batch_number' => $batchNumber,
                'transaction_id' => null,
                'status' => Gift2GamesOrderStatus::PROCESSING,
            ]);
        }

        $item->update(['transaction_id' => $batchNumber, 'status' => PurchaseOrderItemStatus::PROCESSING]);

        Log::info('Gift2Games placeOrder: order placed', [
            'supplier' => $this->supplierSlug,
            'purchase_order_item_id' => $item->id,
            'batch_number' => $batchNumber,
        ]);
    }

    public function updateOrder(PurchaseOrderItem $item): void
    {
        $batchNumber = $item->transaction_id;
        $purchaseOrder = $item->purchaseOrder;

        $pendingOrders = Gift2GamesOrder::where('batch_number', $batchNumber)
            ->where('status', '!=', Gift2GamesOrderStatus::FULFILLED)
            ->get();

        Log::info('Gift2Games updateOrder: started', [
            'supplier_slug' => $this->supplierSlug,
            'purchase_order_item_id' => $item->id,
            'batch_number' => $batchNumber,
            'pending_orders' => $pendingOrders->count(),
        ]);

        $orderData = [
            'productId' => (int) $item->digitalProduct->sku,
            'referenceNumber' => 'order_item_id_'.$item->id,
        ];

        if (! $this->priceMatchesExpected($item)) {
            return;
        }

        foreach ($pendingOrders->chunk(5) as $chunk) {
            $responses = $this->createOrder->execute($orderData, $this->supplierSlug, $chunk->count());

            foreach ($chunk->values() as $index => $order) {
                $response = $responses[$index];

                if ($response === null) {
                    Log::error('Gift2Games updateOrder error', [
                        'supplier_slug' => $this->supplierSlug,
                        'product_item_id' => $item->id,
                        'sku' => $item->digitalProduct->sku ?? null,
                    ]);

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

                $order->update([
                    'transaction_id' => $voucherData['orderId'],
                    'status' => Gift2GamesOrderStatus::FULFILLED,
                ]);
            }
        }

        $hasPending = Gift2GamesOrder::where('batch_number', $item->transaction_id)
            ->where('status', '!=', Gift2GamesOrderStatus::FULFILLED)
            ->exists();

        if (! $hasPending) {
            $item->update(['status' => PurchaseOrderItemStatus::FULFILLED]);
        }

        Log::info('Gift2Games updateOrder: finished', [
            'purchase_order_item_id' => $item->id,
            'batch_number' => $batchNumber,
            'has_pending' => $hasPending,
            'item_status' => $item->status->value,
        ]);
    }

    public function syncProducts(): void
    {
        $this->syncDigitalProducts->syncForSlug($this->supplierSlug);
    }

    /**
     * Verify the live Gift2Games price still matches the price we committed to
     * before spending money. Compares against the purchase order item's
     * unit_cost (locked in at order creation) rather than the digital product's
     * cost_price, which may have drifted after a later product sync. Aborts
     * (returns false) on any mismatch, on a missing price, or when the product
     * cannot be resolved.
     */
    private function priceMatchesExpected(PurchaseOrderItem $item): bool
    {
        $digitalProduct = $item->digitalProduct;

        $liveProduct = $this->getProduct->execute($this->supplierSlug, (int) $digitalProduct->sku);

        $livePrice = $liveProduct['price'] ?? null;
        $expectedPrice = $item->unit_cost;

        // unit_cost comes back as a string via the decimal:2 cast, while the API
        // returns price as a number, so cast both before comparing.
        $matches = $livePrice !== null
            && (float) $livePrice === (float) $expectedPrice;

        if (! $matches) {
            Log::warning('Gift2Games updateOrder: live price does not match purchase order item unit cost, skipping order', [
                'supplier_slug' => $this->supplierSlug,
                'purchase_order_item_id' => $item->id,
                'sku' => $digitalProduct->sku ?? null,
                'live_price' => $livePrice,
                'expected_unit_cost' => $expectedPrice,
            ]);
        }

        return $matches;
    }
}
