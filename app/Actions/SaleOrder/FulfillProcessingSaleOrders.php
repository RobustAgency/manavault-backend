<?php

namespace App\Actions\SaleOrder;

use App\Models\SaleOrder;
use App\Enums\SupplierType;
use App\Models\SaleOrderItem;
use App\Enums\SaleOrderStatus;
use App\Models\DigitalProduct;
use App\Enums\PurchaseOrderStatus;
use App\Events\SaleOrderCompleted;
use Illuminate\Support\Facades\DB;
use App\Services\AutoPurchaseOrderService;
use App\Services\DigitalProductAllocationService;

/**
 * One-off migration action for the SO <-> PO association rollout.
 *
 * Sale orders created before the association existed are stuck in PROCESSING: the new
 * fulfillment logic only completes an order from general stock or from a purchase order
 * linked to it, and these legacy orders have neither a persisted digital product choice
 * nor a linked purchase order. This action replays the new fulfillment logic over every
 * PROCESSING order so each one either completes from existing general stock or gets a
 * properly linked purchase order for its shortfall.
 *
 * Mirrors the post-creation logic of SaleOrderService::createOrder() with two additions
 * required for legacy data: it backfills the item's digital_product_id when missing, and
 * it guards against creating duplicate purchase orders so the command is safe to re-run.
 */
class FulfillProcessingSaleOrders
{
    public function __construct(
        private DigitalProductAllocationService $digitalProductAllocationService,
        private AutoPurchaseOrderService $autoPurchaseOrderService,
    ) {}

    /**
     * @return array<int, array<string, mixed>> one summary row per processed sale order
     */
    public function execute(bool $dryRun = false, ?int $saleOrderId = null): array
    {
        $query = SaleOrder::query()
            ->where('status', SaleOrderStatus::PROCESSING->value)
            ->with(['items.product', 'items.digitalProduct.supplier', 'items.digitalProducts', 'purchaseOrders.items']);

        if ($saleOrderId !== null) {
            $query->where('id', $saleOrderId);
        }

        $summary = [];

        $query->orderBy('id')->chunkById(100, function ($orders) use (&$summary, $dryRun) {
            foreach ($orders as $saleOrder) {
                $summary[] = $this->fulfillOrder($saleOrder, $dryRun);
            }
        });

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private function fulfillOrder(SaleOrder $saleOrder, bool $dryRun): array
    {
        DB::beginTransaction();

        try {
            $fullyAllocated = true;
            $allocatedCount = 0;
            $purchaseOrdersCreated = 0;
            $shortfalls = [];

            foreach ($saleOrder->items as $item) {
                $digitalProduct = $this->resolveDigitalProduct($item);

                if ($digitalProduct === null) {
                    $fullyAllocated = false;
                    logger()->warning('FulfillProcessingSaleOrders: unable to resolve digital product for item', [
                        'sale_order_id' => $saleOrder->id,
                        'sale_order_item_id' => $item->id,
                        'product_id' => $item->product_id,
                    ]);

                    continue;
                }

                $remaining = $item->quantity - $item->digitalProducts()->count();

                if ($remaining <= 0) {
                    continue;
                }

                $allocated = $this->digitalProductAllocationService->allocateFromGeneralStock($item, $digitalProduct, $remaining);
                $allocatedCount += $allocated;

                if ($allocated < $remaining) {
                    $fullyAllocated = false;
                    $shortfalls[] = [
                        'digitalProduct' => $digitalProduct,
                        'remaining' => $remaining - $allocated,
                    ];
                }
            }

            foreach ($shortfalls as ['digitalProduct' => $digitalProduct, 'remaining' => $remaining]) {
                // Only external suppliers can be auto-ordered. Internal stock is added
                // manually, so raising a purchase order would never place or fulfil an
                // order — leave those items PROCESSING for a manual restock instead.
                if (! $this->isExternalSupplier($digitalProduct)) {
                    continue;
                }

                // Idempotency: never raise a second purchase order for a shortfall that an
                // existing, non-failed linked purchase order already covers.
                if ($this->hasOpenLinkedPurchaseOrder($saleOrder, $digitalProduct->id)) {
                    continue;
                }

                if (! $dryRun) {
                    $this->autoPurchaseOrderService->handleShortfall($digitalProduct, $remaining, $saleOrder->id);
                }

                $purchaseOrdersCreated++;
            }

            if ($fullyAllocated) {
                $saleOrder->update(['status' => SaleOrderStatus::COMPLETED->value]);
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            logger()->error('FulfillProcessingSaleOrders: failed to process order', [
                'sale_order_id' => $saleOrder->id,
                'error' => $e->getMessage(),
            ]);

            return $this->summaryRow($saleOrder, 'error', 0, 0, $e->getMessage());
        }

        // Fire the completion event only for a real, committed completion so downstream
        // listeners (customer webhook, etc.) never react to a dry run.
        if ($fullyAllocated && ! $dryRun) {
            event(new SaleOrderCompleted($saleOrder));
        }

        $resultStatus = $fullyAllocated ? SaleOrderStatus::COMPLETED->value : SaleOrderStatus::PROCESSING->value;

        return $this->summaryRow($saleOrder, $resultStatus, $allocatedCount, $purchaseOrdersCreated);
    }

    /**
     * Resolve the digital product to fulfil an item against, persisting the choice on
     * legacy items that predate the sale_order_items.digital_product_id column.
     */
    private function resolveDigitalProduct(SaleOrderItem $item): ?DigitalProduct
    {
        if ($item->digital_product_id !== null) {
            return $item->selectedDigitalProduct;
        }

        $digitalProduct = $item->product?->digitalProduct();

        if ($digitalProduct !== null) {
            $item->update(['digital_product_id' => $digitalProduct->id]);
        }

        return $digitalProduct;
    }

    private function isExternalSupplier(DigitalProduct $digitalProduct): bool
    {
        return $digitalProduct->supplier?->type === SupplierType::EXTERNAL->value;
    }

    private function hasOpenLinkedPurchaseOrder(SaleOrder $saleOrder, int $digitalProductId): bool
    {
        return $saleOrder->purchaseOrders()
            ->where('status', '!=', PurchaseOrderStatus::FAILED->value)
            ->whereHas('items', fn ($query) => $query->where('digital_product_id', $digitalProductId))
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryRow(SaleOrder $saleOrder, string $status, int $allocated, int $purchaseOrdersCreated, ?string $error = null): array
    {
        return [
            'sale_order_id' => $saleOrder->id,
            'order_number' => $saleOrder->order_number,
            'status' => $status,
            'allocated' => $allocated,
            'purchase_orders_created' => $purchaseOrdersCreated,
            'error' => $error,
        ];
    }
}
