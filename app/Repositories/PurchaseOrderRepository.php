<?php

namespace App\Repositories;

use App\Models\PurchaseOrder;
use Illuminate\Pagination\LengthAwarePaginator;

class PurchaseOrderRepository
{
    /**
     * Get paginated purchase orders filtered by the provided criteria.
     * @param array $filters
     * @return LengthAwarePaginator<int, PurchaseOrder>
     */
    public function getPaginatedPurchaseOrders(array $filters = []): LengthAwarePaginator
    {
        $query = PurchaseOrder::query()->with(['product', 'supplier']);

        $perPage = $filters['per_page'] ?? 10;

        return $query->paginate($perPage);
    }

    /**
     * Create a new purchase order.
     * @param array $data
     * @return PurchaseOrder
     */
    public function createPurchaseOrder(array $data): PurchaseOrder
    {
        return PurchaseOrder::create($data);
    }
}
