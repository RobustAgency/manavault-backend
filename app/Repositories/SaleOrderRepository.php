<?php

namespace App\Repositories;

use App\Models\SaleOrder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class SaleOrderRepository
{
    /**
     * Get paginated sale orders filtered by the provided criteria.
     *
     * @return LengthAwarePaginator<int, SaleOrder>
     */
    public function getFilteredSaleOrders(array $filters = []): LengthAwarePaginator
    {
        $query = SaleOrder::with('items.digitalProducts');

        if (isset($filters['order_number'])) {
            $query->where('order_number', 'like', '%'.$filters['order_number'].'%');
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['currency'])) {
            $query->where('currency', $filters['currency']);
        }

        if (isset($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        $per_page = $filters['per_page'] ?? 10;

        return $query->orderBy('created_at', 'desc')->paginate($per_page);
    }

    /**
     * Get all sale orders.
     *
     * @return Collection<int, SaleOrder>
     */
    public function getAllSaleOrders(): Collection
    {
        return SaleOrder::with('items.digitalProducts')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get a sale order by ID with related items and digital products.
     *
     * @throws \Exception
     */
    public function getSaleOrderById(int $id): SaleOrder
    {
        $saleOrder = SaleOrder::with('items.digitalProducts')->find($id);

        if (! $saleOrder) {
            throw new \Exception("Sale order with ID {$id} not found.");
        }

        return $saleOrder;
    }

    /**
     * Get a sale order by order number.
     */
    public function getSaleOrderByOrderNumber(string $orderNumber): ?SaleOrder
    {
        return SaleOrder::with('items.digitalProducts')
            ->where('order_number', $orderNumber)
            ->first();
    }

    /**
     * Create a new sale order.
     */
    public function createSaleOrder(array $data): SaleOrder
    {
        return SaleOrder::create($data);
    }

    /**
     * Update a sale order.
     *
     * @throws \Exception
     */
    public function updateSaleOrder(SaleOrder $saleOrder, array $data): SaleOrder
    {
        $saleOrder->update($data);

        return $saleOrder->fresh('items.digitalProducts');
    }

    /**
     * Delete a sale order.
     *
     * @throws \Exception
     */
    public function deleteSaleOrder(int $id): bool
    {
        $saleOrder = $this->getSaleOrderById($id);

        return $saleOrder->delete();
    }

    /**
     * Get sale orders by status.
     *
     * @return Collection<int, SaleOrder>
     */
    public function getSaleOrdersByStatus(string $status): Collection
    {
        return SaleOrder::with('items.digitalProducts')
            ->where('status', $status)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Check if an order number exists.
     */
    public function orderNumberExists(string $orderNumber): bool
    {
        return SaleOrder::where('order_number', $orderNumber)->exists();
    }

    /**
     * Count total sale orders.
     */
    public function countTotalSaleOrders(): int
    {
        return SaleOrder::count();
    }
}
