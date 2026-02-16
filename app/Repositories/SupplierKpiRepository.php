<?php

namespace App\Repositories;

use App\Models\Supplier;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderSupplier;
use App\Enums\PurchaseOrderSupplierStatus;
use Illuminate\Pagination\LengthAwarePaginator;

class SupplierKpiRepository
{
    /**
     * Get all suppliers KPIs.
     *
     * @return LengthAwarePaginator<int, Supplier>
     */
    public function getFilteredSuppliersKpis(array $filters = []): LengthAwarePaginator
    {
        $query = Supplier::query();

        if (! empty($filters['supplierId'])) {
            $query->where('id', $filters['supplierId']);
        }

        $suppliers = $query->paginate($filters['perPage'] ?? 15);

        if ($suppliers->isEmpty()) {
            return $suppliers;
        }

        $supplierIDs = $suppliers->pluck('id');

        $purchaseOrders = PurchaseOrderSupplier::query()
            ->selectRaw('
            supplier_id,
            COUNT(*) as total_purchase_orders,
            SUM(status = ?) as completed_purchase_orders,
            SUM(status = ?) as processing_purchase_orders
        ', [
                PurchaseOrderSupplierStatus::COMPLETED->value,
                PurchaseOrderSupplierStatus::PROCESSING->value,
            ])
            ->whereIn('supplier_id', $supplierIDs)
            ->groupBy('supplier_id')
            ->get()
            ->keyBy('supplier_id');

        $items = PurchaseOrderItem::query()
            ->selectRaw('
            supplier_id,
            SUM(quantity) as total_quantity_ordered,
            SUM(subtotal) as total_amount_spent
        ')
            ->whereIn('supplier_id', $supplierIDs)
            ->groupBy('supplier_id')
            ->get()
            ->keyBy('supplier_id');

        $suppliers->getCollection()->transform(function ($supplier) use ($purchaseOrders, $items) {

            $id = $supplier->id;

            $purchaseOrders = $purchaseOrders[$id] ?? null;
            $totalPurchaseOrders = (int) ($purchaseOrders->total_purchase_orders ?? 0);
            $completedPurchaseOrders = (int) ($purchaseOrders->completed_purchase_orders ?? 0);
            $processingPurchaseOrders = (int) ($purchaseOrders->processing_purchase_orders ?? 0);

            $items = $items[$id] ?? null;
            $totalQuantityOrdered = (int) ($items->total_quantity_ordered ?? 0);
            $totalAmountSpent = (float) ($items->total_amount_spent ?? 0);

            $averageOrderValue = $totalPurchaseOrders > 0
                ? round($totalAmountSpent / $totalPurchaseOrders, 2)
                : 0;

            $completionRate = $totalPurchaseOrders > 0
                ? round(($completedPurchaseOrders / $totalPurchaseOrders) * 100, 2)
                : 0;

            return [
                'supplier_id' => $id,
                'supplier_name' => $supplier->name,
                'total_purchase_orders' => $totalPurchaseOrders,
                'completed_purchase_orders' => $completedPurchaseOrders,
                'processing_purchase_orders' => $processingPurchaseOrders,
                'total_quantity_ordered' => $totalQuantityOrdered,
                'total_amount_spent' => round($totalAmountSpent, 2),
                'average_order_value' => $averageOrderValue,
                'completion_rate' => $completionRate,
            ];
        });

        return $suppliers;
    }
}
