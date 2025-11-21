<?php

namespace App\Services\PurchaseOrder;

use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Log;
use App\Models\PurchaseOrderSupplier;

class PurchaseOrderStatusService
{
    /**
     * Update the overall purchase order status based on all supplier statuses.
     *
     * This method determines the overall status by evaluating all suppliers:
     * - If ANY supplier has 'failed', overall status is 'failed'
     * - If ANY supplier has 'processing', overall status is 'processing'
     * - If ALL suppliers have 'completed', overall status is 'completed'
     */
    public function updateStatus(PurchaseOrder $purchaseOrder): void
    {
        // Get all purchase order suppliers for this purchase order
        $purchaseOrderSuppliers = PurchaseOrderSupplier::where('purchase_order_id', $purchaseOrder->id)->get();
        $statuses = $purchaseOrderSuppliers->pluck('status');

        // Determine overall status based on priority: failed > processing > completed
        if ($statuses->contains('failed')) {
            $overallStatus = 'failed';
        } elseif ($statuses->contains('processing')) {
            $overallStatus = 'processing';
        } else {
            $overallStatus = 'completed';
        }

        $purchaseOrder->update(['status' => $overallStatus]);

        Log::info('Purchase order status updated', [
            'purchase_order_id' => $purchaseOrder->id,
            'order_number' => $purchaseOrder->order_number,
            'status' => $overallStatus,
            'supplier_count' => $purchaseOrderSuppliers->count(),
            'supplier_statuses' => $statuses->toArray(),
        ]);
    }
}
