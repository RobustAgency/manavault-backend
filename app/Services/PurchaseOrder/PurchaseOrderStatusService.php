<?php

namespace App\Services\PurchaseOrder;

use App\Models\PurchaseOrder;
use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseOrderSupplierStatus;

class PurchaseOrderStatusService
{
    public function updateStatus(PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->load('vouchers');
        $totalVouchers = $purchaseOrder->vouchers()->count();

        // Get the total quantity from all purchase order items
        $expectedQuantity = $purchaseOrder->totalQuantity();

        // Check if all vouchers have available status
        $availableVouchers = $purchaseOrder->vouchers()
            ->where('status', 'available')
            ->count();

        if ($availableVouchers == $expectedQuantity) {
            $purchaseOrder->update(['status' => PurchaseOrderStatus::COMPLETED->value]);
            $purchaseOrder->purchaseOrderSuppliers()->update(['status' => PurchaseOrderSupplierStatus::COMPLETED->value]);
        }
    }
}
