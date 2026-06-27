<?php

namespace App\Actions;

use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\DigitalProduct;
use App\Enums\SaleOrder\Status;
use App\Events\SaleOrderCompleted;
use Illuminate\Support\Facades\DB;
use App\Services\VoucherAllocationService;

class AssignVouchersToSaleOrderAction
{
    public function __construct(
        private VoucherAllocationService $voucherAllocationService,
    ) {}

    public function execute(SaleOrder $saleOrder): array
    {
        if ($saleOrder->status === Status::COMPLETED->value) {
            return ['already_completed' => true, 'fully_allocated' => false, 'summary' => []];
        }

        DB::beginTransaction();
        try {
            $fullyAllocated = true;
            $summary = [];

            foreach ($saleOrder->items as $item) {
                $alreadyAllocated = $item->digitalProducts()->count();
                $remaining = $item->quantity - $alreadyAllocated;

                if ($remaining <= 0) {
                    $summary[] = [$item->product->name, $item->quantity, $alreadyAllocated, 0, 'Already fulfilled'];

                    continue;
                }

                // Allocate against the digital product selected for the item at order creation
                // time (persisted on sale_order_items.digital_product_id), not a live re-resolution
                // of the mutable Product → DigitalProduct association.
                $digitalProduct = $item->selectedDigitalProduct;

                if (! $digitalProduct) {
                    $summary[] = [$item->product->name, $item->quantity, $alreadyAllocated, 0, 'No digital product selected'];
                    $fullyAllocated = false;

                    continue;
                }

                $allocated = $this->allocate($item, $digitalProduct, $remaining);

                $status = $allocated >= $remaining
                    ? "Fulfilled ({$digitalProduct->sku})"
                    : "Partial — insufficient stock ({$digitalProduct->sku})";

                $summary[] = [$item->product->name, $item->quantity, $alreadyAllocated, $allocated, $status];

                if ($allocated < $remaining) {
                    $fullyAllocated = false;
                }
            }

            if ($fullyAllocated) {
                $saleOrder->update(['status' => Status::COMPLETED->value]);
                DB::commit();
                event(new SaleOrderCompleted($saleOrder));

                return ['already_completed' => false, 'fully_allocated' => true, 'summary' => $summary];
            }

            DB::rollBack();

            return ['already_completed' => false, 'fully_allocated' => false, 'summary' => $summary];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Allocate up to $remaining available (general-stock) vouchers of the given digital
     * product to the item. Returns the number actually allocated.
     */
    private function allocate(SaleOrderItem $item, DigitalProduct $digitalProduct, int $remaining): int
    {
        $vouchers = $this->voucherAllocationService->getAvailableVouchers($digitalProduct->id);

        $allocated = 0;
        foreach ($vouchers as $voucher) {
            if ($allocated >= $remaining) {
                break;
            }

            $this->voucherAllocationService->allocateVoucher($item->id, $digitalProduct, $voucher);
            $allocated++;
        }

        return $allocated;
    }
}
