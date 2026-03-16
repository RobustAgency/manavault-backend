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

class ProcessPendingSaleOrders implements ShouldQueue
{
    public function __construct(
        private DigitalProductAllocationService $digitalProductAllocationService,
        private SaleOrderRepository $saleOrderRepository,
    ) {}

    public function handle(NewVouchersAvailable $event): void
    {
        $digitalProductIds = $event->digitalProductIds;
        Log::info('FulfillPendingSaleOrders: triggered with new vouchers for digital products', [
            'digital_product_ids' => $digitalProductIds,
        ]);
        $pendingOrders = $this->saleOrderRepository->getPendingSaleOrdersForDigitalProducts($digitalProductIds);

        foreach ($pendingOrders as $saleOrder) {
            $this->processSaleOrder($saleOrder);
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
                $allocated = $this->digitalProductAllocationService->allocate($item, $product, $remaining);

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
