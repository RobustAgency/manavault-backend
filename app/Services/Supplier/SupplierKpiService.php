<?php

namespace App\Services\Supplier;

use App\Models\Supplier;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderSupplier;
use App\Enums\PurchaseOrderSupplierStatus;

class SupplierKpiService
{
    public function getSupplierKpis(Supplier $supplier): array
    {
        $supplierId = $supplier->id;

        $supplierOrdersQuery = PurchaseOrderSupplier::query()
            ->where('supplier_id', $supplierId);

        $totalPurchaseOrders = $supplierOrdersQuery->count();
        $completedPurchaseOrders = $supplierOrdersQuery
            ->where('status', PurchaseOrderSupplierStatus::COMPLETED->value)
            ->count();
        $processingPurchaseOrders = $supplierOrdersQuery
            ->where('status', PurchaseOrderSupplierStatus::PROCESSING->value)
            ->count();

        $itemsQuery = PurchaseOrderItem::query()
            ->where('supplier_id', $supplierId);

        $totalQuantityOrdered = (int) $itemsQuery->sum('quantity');
        $totalAmountSpent = (float) $itemsQuery->sum('subtotal');

        $completionRate = $totalPurchaseOrders > 0
            ? round(($completedPurchaseOrders / $totalPurchaseOrders) * 100, 2)
            : 0.0;

        $averageOrderValue = $totalPurchaseOrders > 0
            ? round($totalAmountSpent / $totalPurchaseOrders, 2)
            : 0.0;

        return [
            'supplier_id' => $supplierId,
            'total_purchase_orders' => $totalPurchaseOrders,
            'completed_purchase_orders' => $completedPurchaseOrders,
            'processing_purchase_orders' => $processingPurchaseOrders,
            'total_quantity_ordered' => $totalQuantityOrdered,
            'total_amount_spent' => round($totalAmountSpent, 2),
            'average_order_value' => $averageOrderValue,
            'completion_rate' => $completionRate,
        ];
    }
}
