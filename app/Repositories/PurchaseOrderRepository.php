<?php

namespace App\Repositories;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderSupplier;
use Illuminate\Pagination\LengthAwarePaginator;

class PurchaseOrderRepository
{
    /**
     * Get paginated purchase orders filtered by the provided criteria.
     *
     * @return LengthAwarePaginator<int, PurchaseOrder>
     */
    public function getFilteredPurchaseOrders(array $filters = []): LengthAwarePaginator
    {
        $query = PurchaseOrder::with(['items', 'suppliers', 'vouchers']);

        if (isset($filters['supplier_id'])) {
            $query->whereHas('purchaseOrderSuppliers', function ($q) use ($filters) {
                $q->where('supplier_id', $filters['supplier_id']);
            });
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['order_number'])) {
            $query->where('order_number', 'like', '%'.$filters['order_number'].'%');
        }

        $perPage = $filters['per_page'] ?? 10;

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Persist a new PurchaseOrder record to the database.
     */
    public function createPurchaseOrder(array $attributes): PurchaseOrder
    {
        return PurchaseOrder::create($attributes);
    }

    /**
     * Persist a new PurchaseOrderSupplier record to the database.
     */
    public function createPurchaseOrderSupplier(array $attributes): PurchaseOrderSupplier
    {
        return PurchaseOrderSupplier::create($attributes);
    }

    /**
     * Persist a new PurchaseOrderItem record to the database.
     */
    public function createPurchaseOrderItem(array $attributes): PurchaseOrderItem
    {
        return PurchaseOrderItem::create($attributes);
    }

    /**
     * Find a PurchaseOrder by ID, eager-loading items.
     */
    public function getPurchaseOrderById(int $id): PurchaseOrder
    {
        /** @var PurchaseOrder|null $purchaseOrder */
        $purchaseOrder = PurchaseOrder::with('items')->find($id);

        if (! $purchaseOrder) {
            throw new \RuntimeException('Purchase order not found with ID: '.$id);
        }

        return $purchaseOrder;
    }
}
