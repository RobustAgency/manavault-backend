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

class ProcessVoucherCodes implements ShouldQueue
{
    public function __construct(
        private SaleOrderRepository $saleOrderRepository,
        private DigitalProductAllocationService $digitalProductAllocationService,
    ) {}

    public function handle(NewVouchersAvailable $event): void
    {
        if ($event->saleOrderId === null) {
            return;
        }

        $saleOrder = $this->saleOrderRepository->getSaleOrderById($event->saleOrderId);

        if ($saleOrder->status === Status::PROCESSING->value) {
            $this->processSaleOrder($saleOrder, $event->digitalProductIds);
        }
    }

    private function processSaleOrder(SaleOrder $saleOrder, array $arrivedDigitalProductIds): void
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

                // Use the digital product selected for this item at order creation time
                $digitalProduct = $item->selectedDigitalProduct;

                // Only attempt allocation for items whose product received new vouchers.
                // Items waiting on a different batch will be handled by their own event.
                if ($digitalProduct === null || ! in_array($digitalProduct->id, $arrivedDigitalProductIds)) {
                    $fullyAllocated = false;

                    continue;
                }

                $allocated = $this->digitalProductAllocationService->allocateFromLinkedPurchaseOrder($item, $digitalProduct, $remaining, $saleOrder->id);

                if ($allocated < $remaining) {
                    $fullyAllocated = false;
                }
            }

            if ($fullyAllocated) {
                $saleOrder->update(['status' => Status::COMPLETED->value]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('FulfillPendingSaleOrders: failed to fulfil order', [
                'sale_order_id' => $saleOrder->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if ($fullyAllocated) {
            event(new SaleOrderCompleted($saleOrder));
        }
    }
}
