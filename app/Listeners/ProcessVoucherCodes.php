<?php

namespace App\Listeners;

use App\Services\SaleOrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Events\NewVouchersAvailable;
use App\Repositories\SaleOrderRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Services\DigitalProductAllocationService;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ProcessVoucherCodes implements ShouldQueue
{
    public function __construct(
        private SaleOrderRepository $saleOrderRepository,
        private DigitalProductAllocationService $digitalProductAllocationService,
        private SaleOrderService $saleOrderService,
    ) {}

    /**
     * Serialize processing per sale order. Voucher arrivals for the same order
     * fire this listener repeatedly, and concurrent runs would each read the
     * same allocation state and double-allocate. Different orders still run in
     * parallel.
     *
     * @return array<int, object>
     */
    public function middleware(NewVouchersAvailable $event): array
    {
        $purchaseOrderItem = $event->purchaseOrderItem;

        return [
            (new WithoutOverlapping("process-voucher-codes:{$purchaseOrderItem->id}"))
                ->releaseAfter(10)
                ->expireAfter(120),
        ];
    }

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
                // Only allocate what the item still needs.
                $remaining = $item->quantity - $item->digitalProducts()->count();

                if ($remaining <= 0) {
                    continue;
                }

                $this->digitalProductAllocationService->allocateFromLinkedPurchaseOrder($item, $item->digitalProduct, $remaining, $saleOrder->id);
            }

            // Persisting the status fires SaleOrderUpdated, which dispatches the outbound
            // webhook via the DispatchSaleOrderStatusEvents listener.
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
