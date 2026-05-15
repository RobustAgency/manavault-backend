<?php

namespace App\Services\PurchaseOrder;

use App\Enums\SupplierType;
use App\Models\PurchaseOrder;
use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseOrderSupplierStatus;

class PurchaseOrderStatusService
{
    /**
     * Evaluate the statuses of all PurchaseOrderSuppliers
     */
    public function updateStatus(PurchaseOrder $purchaseOrder): void
    {
        $finalStatuses = [
            PurchaseOrderSupplierStatus::COMPLETED->value,
            PurchaseOrderSupplierStatus::FAILED->value,
        ];

        $suppliers = $purchaseOrder->purchaseOrderSuppliers()->get(['status']);

        if ($suppliers->isEmpty()) {
            return;
        }

        $allInFinalState = $suppliers->every(
            fn ($supplier) => in_array($supplier->status, $finalStatuses, true)
        );

        if (! $allInFinalState) {
            return;
        }

        $allFailed = $suppliers->every(
            fn ($supplier) => $supplier->status === PurchaseOrderSupplierStatus::FAILED->value
        );

        $anyFailed = $suppliers->contains(
            fn ($supplier) => $supplier->status === PurchaseOrderSupplierStatus::FAILED->value
        );

        $anyCompleted = $suppliers->contains(
            fn ($supplier) => $supplier->status === PurchaseOrderSupplierStatus::COMPLETED->value
        );

        if ($allFailed) {
            $purchaseOrder->update(['status' => PurchaseOrderStatus::FAILED->value]);
        } elseif ($anyFailed && $anyCompleted) {
            $purchaseOrder->update(['status' => PurchaseOrderStatus::PARTIAL->value]);
        } elseif (! $anyFailed) {
            $purchaseOrder->update(['status' => PurchaseOrderStatus::COMPLETED->value]);
        }

    }

    public function updateInternalSuppliersStatusToCompleted(PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->purchaseOrderSuppliers()
            ->whereHas('supplier', fn ($query) => $query->where('type', SupplierType::INTERNAL->value))
            ->update(['status' => PurchaseOrderSupplierStatus::COMPLETED->value]);

        $this->updateStatus($purchaseOrder);
    }
}
