<?php

namespace App\Services;

use App\Models\SaleOrder;
use App\Models\PurchaseOrder;
use App\Enums\SaleOrder\Status;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Repositories\SaleOrderRepository;

/**
 * Handles allocation for manually created purchase orders.
 */
class ManualPurchaseOrderStockService
{
    public function __construct(
        private SaleOrderRepository $saleOrderRepository,
        private DigitalProductAllocationService $digitalProductAllocationService,
    ) {}

    /**
     * Process a manually created purchase order and allocate its stock to pending sale orders.
     */
    public function processPurchaseOrderStock(int $purchaseOrderId): void
    {
        DB::transaction(function () use ($purchaseOrderId) {
            $processingSaleOrders = $this->saleOrderRepository->getProcessingSaleOrdersDueToFailedPurchaseOrders();

            if ($processingSaleOrders->isEmpty()) {
                Log::info('No PROCESSING sale orders found for manual purchase order allocation', [
                    'purchase_order_id' => $purchaseOrderId,
                ]);

                return;
            }

            $purchaseOrder = PurchaseOrder::with('items')->findOrFail($purchaseOrderId);

            $itemsByDigitalProduct = $purchaseOrder->items->groupBy('digital_product_id');

            foreach ($itemsByDigitalProduct as $digitalProductId => $purchaseItems) {
                $totalQuantity = collect($purchaseItems)->sum('quantity');

                // Try to allocate this digital product's stock to processing sale orders
                $this->allocateToProcessingSaleOrders(
                    $processingSaleOrders,
                    (int) $digitalProductId,
                    $totalQuantity,
                    $purchaseOrder
                );
            }
        });
    }

    /**
     * Allocate digital product stock from the purchase order to PROCESSING sale orders.
     *
     * @param  \Illuminate\Support\Collection<int, SaleOrder>  $processingSaleOrders
     */
    private function allocateToProcessingSaleOrders(
        \Illuminate\Support\Collection $processingSaleOrders,
        int $digitalProductId,
        int $availableQuantity,
        PurchaseOrder $purchaseOrder
    ): void {
        $remainingQuantity = $availableQuantity;

        foreach ($processingSaleOrders as $saleOrder) {
            if ($remainingQuantity <= 0) {
                break;
            }

            // Check if this sale order has items that need this digital product
            foreach ($saleOrder->items as $saleOrderItem) {
                if ($remainingQuantity <= 0) {
                    break;
                }

                // Get the digital product for this sale order item
                $product = $saleOrderItem->product;
                if (! $product) {
                    continue;
                }

                // Check if this product uses the digital product we're trying to allocate
                $productDigitalProducts = $product->digitalProducts;
                $matches = $productDigitalProducts->where('id', $digitalProductId)->first();

                if (! $matches) {
                    continue;
                }

                // Determine how many units of this sale order item we can fulfill
                $allocatedForItem = $this->digitalProductAllocationService->allocate(
                    $saleOrderItem,
                    $product,
                    min($saleOrderItem->quantity, $remainingQuantity)
                );

                $remainingQuantity -= $allocatedForItem;

                Log::info('Allocated stock from manual purchase order to sale order item', [
                    'purchase_order_id' => $purchaseOrder->id,
                    'sale_order_id' => $saleOrder->id,
                    'sale_order_item_id' => $saleOrderItem->id,
                    'digital_product_id' => $digitalProductId,
                    'quantity_allocated' => $allocatedForItem,
                ]);
            }

            // Check if this sale order is now fully fulfilled and update its status
            $this->checkAndUpdateSaleOrderStatus($saleOrder);
        }
    }

    /**
     * Check if a sale order is now fully fulfilled and update its status accordingly.
     */
    private function checkAndUpdateSaleOrderStatus(SaleOrder $saleOrder): void
    {
        $saleOrder->refresh();

        $totalRequired = $saleOrder->items->sum('quantity');
        $totalAllocated = $saleOrder->items->sum(function ($item) {
            return $item->digitalProducts->count();
        });

        if ($totalAllocated >= $totalRequired) {
            $saleOrder->update(['status' => Status::COMPLETED->value]);
            Log::info('Sale order marked as COMPLETED after stock allocation', [
                'sale_order_id' => $saleOrder->id,
            ]);
        }
    }
}
