<?php

namespace App\Services\PurchaseOrder;

use App\Models\Voucher;
use App\Models\PurchaseOrder;
use App\Enums\PurchaseOrderStatus;
use App\Events\PurchaseOrderFulfill;
use App\Enums\PurchaseOrderSupplierStatus;

class PurchaseOrderStatusService
{
    /**
     * Re-evaluate every supplier's voucher fulfilment, promote any that are now
     * complete, then derive and persist the overall PurchaseOrder status.
     *
     * Per-supplier promotion rules (only applies to suppliers still in "processing"):
     *   - Count available vouchers for the supplier's purchase order items.
     *   - If the count matches the expected quantity → mark supplier as "completed".
     *
     * Overall order status rules:
     *   - All completed                          → completed
     *   - All failed                             → failed
     *   - completed + processing (± failed)      → processing
     *   - completed + failed (no processing)     → completed
     *   - Any other combination with processing  → processing
     */
    public function updateStatus(PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->load(['purchaseOrderSuppliers', 'items']);

        // --- Step 1: promote any processing supplier whose vouchers are now fully uploaded ---
        foreach ($purchaseOrder->purchaseOrderSuppliers as $purchaseOrderSupplier) {
            $currentStatus = PurchaseOrderSupplierStatus::from((string) $purchaseOrderSupplier->status);

            // Only consider suppliers that are still waiting for vouchers
            if ($currentStatus !== PurchaseOrderSupplierStatus::PROCESSING) {
                continue;
            }

            $supplierId = $purchaseOrderSupplier->supplier_id;

            // Items that belong to this supplier within the purchase order
            $supplierItems = $purchaseOrder->items->where('supplier_id', $supplierId);

            $expectedQuantity = $supplierItems->sum('quantity');

            // Count available vouchers directly for the items belonging to this supplier
            $itemIds = $supplierItems->pluck('id')->toArray();
            $availableVouchers = Voucher::whereIn('purchase_order_item_id', $itemIds)
                ->where('status', 'available')
                ->count();

            if ($expectedQuantity > 0 && $availableVouchers >= $expectedQuantity) {
                $purchaseOrderSupplier->update(['status' => PurchaseOrderSupplierStatus::COMPLETED->value]);
            }
        }

        // Refresh so the statuses computed above are reflected in the collection
        $purchaseOrder->load('purchaseOrderSuppliers');

        // --- Step 2: derive and persist the overall order status ---
        $uniqueStatuses = $purchaseOrder->purchaseOrderSuppliers
            ->pluck('status')
            ->map(fn ($s) => $s instanceof PurchaseOrderSupplierStatus ? $s->value : $s)
            ->unique()
            ->values()
            ->toArray();

        $overallStatus = $this->deriveOrderStatus($uniqueStatuses);

        $purchaseOrder->update(['status' => $overallStatus->value]);

        if ($overallStatus === PurchaseOrderStatus::COMPLETED) {
            event(new PurchaseOrderFulfill($purchaseOrder));
        }
    }

    /**
     * Determine the PurchaseOrder status from a unique list of supplier status strings.
     *
     * @param  string[]  $uniqueStatuses
     */
    private function deriveOrderStatus(array $uniqueStatuses): PurchaseOrderStatus
    {
        $hasCompleted = in_array(PurchaseOrderSupplierStatus::COMPLETED->value, $uniqueStatuses, true);
        $hasProcessing = in_array(PurchaseOrderSupplierStatus::PROCESSING->value, $uniqueStatuses, true);
        $hasFailed = in_array(PurchaseOrderSupplierStatus::FAILED->value, $uniqueStatuses, true);

        // All suppliers share the same single status
        if (count($uniqueStatuses) === 1) {
            return match ($uniqueStatuses[0]) {
                PurchaseOrderSupplierStatus::COMPLETED->value => PurchaseOrderStatus::COMPLETED,
                PurchaseOrderSupplierStatus::FAILED->value => PurchaseOrderStatus::FAILED,
                default => PurchaseOrderStatus::PROCESSING,
            };
        }

        // Any mix that still contains a processing supplier → processing
        if ($hasProcessing) {
            return PurchaseOrderStatus::PROCESSING;
        }

        // completed + failed (no processing) → completed
        if ($hasCompleted && $hasFailed) {
            return PurchaseOrderStatus::COMPLETED;
        }

        // Fallback
        return PurchaseOrderStatus::PROCESSING;
    }
}
