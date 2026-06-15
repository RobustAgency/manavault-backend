<?php

namespace App\Listeners;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Events\NewVouchersAvailable;
use App\Services\SaleOrderService;
use App\Repositories\SaleOrderRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Services\DigitalProductAllocationService;

class ProcessVoucherCodes implements ShouldQueue
{
    public function __construct(
        private SaleOrderRepository $saleOrderRepository,
        private DigitalProductAllocationService $digitalProductAllocationService,
        private SaleOrderService $saleOrderService,
    ) {}

    public function handle(NewVouchersAvailable $event): void
    {
        $purchaseOrderItem = $event->purchaseOrderItem;
        $saleOrderId = $purchaseOrderItem->purchaseOrder?->sale_order_id;

        if (! $saleOrderId) {
            Log::warning('Manual Purchase Order: No associated sale order for purchase order item', [
                'purchase_order_item_id' => $purchaseOrderItem->id,
            ]);

            return;
        }

        $saleOrder = $this->saleOrderRepository->getSaleOrderById($saleOrderId);

        DB::beginTransaction();
        try {
            foreach ($saleOrder->items as $item) {
                $remaining = $item->quantity - $item->digitalProducts()->count();

                if ($remaining <= 0) {
                    continue;
                }

                $this->digitalProductAllocationService->allocateFromLinkedPurchaseOrder($item, $item->product, $remaining, $saleOrder->id);
            }

            // Persisting the status fires SaleOrderUpdated, which dispatches the
            // SaleOrderFulfillmentUpdated event via the DispatchSaleOrderStatusEvents listener.
            $this->saleOrderService->updateStatus($saleOrder);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('FulfillPendingSaleOrders: failed to fulfil order', [
                'sale_order_id' => $saleOrder->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }
    }
}
