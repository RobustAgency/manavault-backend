<?php

namespace App\Repositories;

use App\Models\PriceRuleProduct;
use Illuminate\Pagination\LengthAwarePaginator;

class PriceRuleProductRepository
{
    /**
     * Create a new price rule product application record.
     */
    public function create(array $data): PriceRuleProduct
    {
        return PriceRuleProduct::create($data);
    }

    /**
     * Get all applications for a specific price rule with pagination.
     *
     * @return LengthAwarePaginator<int, PriceRuleProduct>
     */
    public function getByPriceRule(int $priceRuleId, int $perPage = 15): LengthAwarePaginator
    {
        return PriceRuleProduct::with('product')
            ->where('price_rule_id', $priceRuleId)
            ->latest('applied_at')
            ->paginate($perPage);
    }

    /**
     * Get all applications for a specific product with pagination.
     *
     * @return LengthAwarePaginator<int, PriceRuleProduct>
     */
    public function getByProduct(int $productId, int $perPage = 15): LengthAwarePaginator
    {
        return PriceRuleProduct::with('priceRule')
            ->where('product_id', $productId)
            ->latest('applied_at')
            ->paginate($perPage);
    }

    /**
     * Get all applications with optional filters and pagination.
     *
     * @return LengthAwarePaginator<int, PriceRuleProduct>
     */
    public function getFilteredPriceRuleProducts(array $filters = []): LengthAwarePaginator
    {
        $query = PriceRuleProduct::with(['product', 'priceRule']);

        if (! empty($filters['price_rule_id'])) {
            $query->where('price_rule_id', $filters['price_rule_id']);
        }

        if (! empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('applied_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('applied_at', '<=', $filters['date_to']);
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->latest('applied_at')->paginate($perPage);
    }

    /**
     * Find a single application by ID.
     */
    public function findById(int $id): ?PriceRuleProduct
    {
        return PriceRuleProduct::with(['product', 'priceRule'])->find($id);
    }
}
