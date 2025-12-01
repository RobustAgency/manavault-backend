<?php

namespace App\Repositories;

use App\Models\DigitalProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

class DigitalStockRepository
{
    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, DigitalProduct>
     */
    public function getPaginatedDigitalStocks(array $filters = [])
    {
        $query = $this->buildBaseQuery($filters);
        $perPage = $filters['per_page'] ?? 15;

        return $query->paginate($perPage);
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, DigitalProduct>
     */
    public function getLowDigitalStocks(array $filters = [])
    {
        $query = $this->buildBaseQuery($filters);

        $threshold = DigitalProduct::LOW_QUANTITY_THRESHOLD;
        $query->where(function (Builder $query) use ($threshold) {
            $query->whereRaw('COALESCE(poi_totals.total_quantity, 0) < ?', [$threshold]);
        });

        $perPage = $filters['per_page'] ?? 15;

        return $query->paginate($perPage);
    }

    /**
     * Build the base query for digital products with supplier and quantity joins
     *
     * @return \Illuminate\Database\Eloquent\Builder<DigitalProduct>
     */
    private function buildBaseQuery(array $filters = []): Builder
    {
        $quantitySubquery = DB::table('purchase_order_items')
            ->select('digital_product_id')
            ->selectRaw('SUM(quantity) as total_quantity')
            ->groupBy('digital_product_id');

        $query = DigitalProduct::query();

        $query->join('suppliers', 'digital_products.supplier_id', '=', 'suppliers.id')
            ->leftJoinSub($quantitySubquery, 'poi_totals', function ($join) {
                $join->on('digital_products.id', '=', 'poi_totals.digital_product_id');
            });

        $query->select([
            'digital_products.*',
            'suppliers.name as supplier_name',
            'suppliers.type as supplier_type',
            DB::raw('COALESCE(poi_totals.total_quantity, 0) as quantity'),
        ]);

        $query->where(function (Builder $query) {
            $query->where('suppliers.type', 'internal')
                ->orWhere(function (Builder $subQuery) {
                    $subQuery->where('suppliers.type', 'external')
                        ->whereRaw('COALESCE(poi_totals.total_quantity, 0) > 0');
                });
        });

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

        $query->orderBy('digital_products.id');

        return $query;
    }
}
