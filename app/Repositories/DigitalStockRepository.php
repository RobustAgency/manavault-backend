<?php

namespace App\Repositories;

use App\Models\DigitalProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

class DigitalStockRepository
{
    // / Define a constant for low stock threshold
    const LOW_STOCK_THRESHOLD = DigitalProduct::LOW_QUANTITY_THRESHOLD;

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, DigitalProduct>
     */
    public function getFilteredDigitalStocks(array $filters = [])
    {
        $query = $this->buildBaseQuery($filters);

        if (! empty($filters['stock']) && $filters['stock'] === 'low') {
            $query = $this->getLowDigitalStocks($query);
        }

        if (! empty($filters['stock']) && $filters['stock'] === 'high') {
            $query = $this->getHighDigitalStocks($query);
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->paginate($perPage);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<DigitalProduct>  $query
     * @return \Illuminate\Database\Eloquent\Builder<DigitalProduct>
     */
    private function getLowDigitalStocks(Builder $query): Builder
    {
        $threshold = self::LOW_STOCK_THRESHOLD;
        $query->where(function (Builder $query) use ($threshold) {
            $query->whereRaw('COALESCE(available_vouchers.available_quantity, 0) < ?', [$threshold]);
        });

        return $query;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<DigitalProduct>  $query
     * @return \Illuminate\Database\Eloquent\Builder<DigitalProduct>
     */
    private function getHighDigitalStocks(Builder $query): Builder
    {
        $threshold = self::LOW_STOCK_THRESHOLD;
        $query->where(function (Builder $query) use ($threshold) {
            $query->whereRaw('COALESCE(available_vouchers.available_quantity, 0) >= ?', [$threshold]);
        });

        return $query;
    }

    /**
     * Build the base query for digital products with supplier and quantity joins
     *
     * @return \Illuminate\Database\Eloquent\Builder<DigitalProduct>
     */
    private function buildBaseQuery(array $filters = []): Builder
    {
        $availableVouchersSubquery = DB::table('vouchers')
            ->select('purchase_order_items.digital_product_id')
            ->selectRaw('COUNT(*) as available_quantity')
            ->join('purchase_order_items', 'vouchers.purchase_order_item_id', '=', 'purchase_order_items.id')
            ->where('vouchers.status', 'available')
            ->groupBy('purchase_order_items.digital_product_id');

        $query = DigitalProduct::query();

        $query->join('suppliers', 'digital_products.supplier_id', '=', 'suppliers.id')
            ->leftJoinSub($availableVouchersSubquery, 'available_vouchers', function ($join) {
                $join->on('digital_products.id', '=', 'available_vouchers.digital_product_id');
            });

        $query->select([
            'digital_products.*',
            'suppliers.name as supplier_name',
            'suppliers.type as supplier_type',
            DB::raw('COALESCE(available_vouchers.available_quantity, 0) as quantity'),
        ]);

        // Apply filters
        if (isset($filters['supplier_id'])) {
            $query->where('digital_products.supplier_id', $filters['supplier_id']);
        }

        if (isset($filters['name'])) {
            $query->where('digital_products.name', 'like', '%'.$filters['name'].'%');
        }

        if (isset($filters['brand'])) {
            $query->where('digital_products.brand', 'like', '%'.$filters['brand'].'%');
        }

        $query->orderBy('digital_products.created_at', 'desc');

        return $query;
    }

    /**
     * Get total available voucher count for a specific digital product
     */
    public function getDigitalProductQuantity(int $digitalProductId): int
    {
        $result = DB::table('vouchers')
            ->join('purchase_order_items', 'vouchers.purchase_order_item_id', '=', 'purchase_order_items.id')
            ->where('purchase_order_items.digital_product_id', $digitalProductId)
            ->where('vouchers.status', 'available')
            ->count();

        return $result;
    }
}
