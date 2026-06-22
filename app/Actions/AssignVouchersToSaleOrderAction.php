<?php

namespace App\Actions;

use App\Enums\Product\FulfillmentMode;
use App\Enums\SaleOrder\Status;
use App\Events\SaleOrderCompleted;
use App\Models\DigitalProduct;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Services\VoucherAllocationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AssignVouchersToSaleOrderAction
{
    public function __construct(
        private VoucherAllocationService $voucherAllocationService,
    ) {}

    /**
     * @return array{already_completed: bool, fully_allocated: bool, summary: list<array<int, string>>}
     */
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

                [$allocated, $usedDigitalProduct] = $this->allocateWithFallback($item, $remaining);

                if ($usedDigitalProduct) {
                    $status = $allocated >= $remaining
                        ? "Fulfilled ({$usedDigitalProduct->sku})"
                        : "Partial — insufficient stock ({$usedDigitalProduct->sku})";
                } else {
                    $status = 'No stock available';
                }

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
     * Try digital products in priority order, skipping those with no available vouchers.
     * Allocates from the first digital product that has stock.
     *
     * @return array{0: int, 1: ?DigitalProduct}
     */
    private function allocateWithFallback(SaleOrderItem $item, int $remaining): array
    {
        foreach ($this->getOrderedDigitalProducts($item->product) as $digitalProduct) {
            $vouchers = $this->voucherAllocationService->getAvailableVouchersForDigitalProduct($digitalProduct->id);

            if ($vouchers->isEmpty()) {
                continue;
            }

            $allocated = 0;
            foreach ($vouchers as $voucher) {
                if ($allocated >= $remaining) {
                    break;
                }
                $this->voucherAllocationService->allocateVoucher($item->id, $digitalProduct, $voucher);
                $allocated++;
            }

            return [$allocated, $digitalProduct];
        }

        return [0, null];
    }

    /**
     * @return Collection<int, DigitalProduct>
     */
    private function getOrderedDigitalProducts(Product $product): Collection
    {
        $query = $product->digitalProducts();

        return $product->fulfillment_mode === FulfillmentMode::MANUAL->value
            ? $query->orderByPivot('priority')->get()
            : $query->orderBy('digital_products.cost_price')->get();
    }
}
