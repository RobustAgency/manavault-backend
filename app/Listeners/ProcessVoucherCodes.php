<?php

namespace App\Listeners;

use App\Models\SaleOrder;
use App\Enums\SaleOrder\Status;
use App\Events\SaleOrderCompleted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Events\NewVouchersAvailable;
use App\Repositories\SaleOrderRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Services\DigitalProductAllocationService;
use App\Services\ManualPurchaseOrderStockService;

class ProcessVoucherCodes implements ShouldQueue
{
    public function __construct(
        private SaleOrderRepository $saleOrderRepository,
        private DigitalProductAllocationService $digitalProductAllocationService,
        private ManualPurchaseOrderStockService $manualPurchaseOrderStockService,
    ) {}

    public function handle(NewVouchersAvailable $event): void
    {
        logger()->info('ProcessVoucherCodes: handling NewVouchersAvailable event', [
            'sale_order_id' => $event->saleOrderId,
            'purchase_order_id' => $event->purchaseOrderId,
            'event' => $event,
        ]);
        $digitalProductIds = $event->digitalProductIds;
        Log::info('FulfillPendingSaleOrders: triggered with new vouchers for digital products', [
            'digital_product_ids' => $digitalProductIds,
        ]);

        if ($event->saleOrderId !== null) {
            $saleOrder = $this->saleOrderRepository->getSaleOrderById($event->saleOrderId);
            if ($saleOrder->status === Status::PROCESSING->value) {
                $this->processSaleOrder($saleOrder);
            }
        } else {
            // process purchase order without sale order id
            $this->manualPurchaseOrderStockService->processPurchaseOrderStock($event->purchaseOrderId);
        }
    }

    private function processSaleOrder(SaleOrder $saleOrder): void
    {
        DB::beginTransaction();
        try {
            $fullyAllocated = true;

            foreach ($saleOrder->items as $item) {
                $alreadyAllocated = $item->digitalProducts()->count();
                $remaining = $item->quantity - $alreadyAllocated;

                if ($remaining <= 0) {
                    continue;
                }

                $product = $item->product;
                $allocated = $this->digitalProductAllocationService->allocate($item, $product, $remaining, $saleOrder->id);

                if ($allocated < $remaining) {
                    $fullyAllocated = false;
                }
            }

            if ($fullyAllocated) {
                $saleOrder->update(['status' => Status::COMPLETED->value]);
                DB::commit();
                event(new SaleOrderCompleted($saleOrder));
            } else {
                // Do not partially persist this retry attempt
                DB::rollBack();
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('FulfillPendingSaleOrders: failed to fulfil order', [
                'sale_order_id' => $saleOrder->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
