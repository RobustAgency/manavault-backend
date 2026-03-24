<?php

namespace App\Repositories;

use App\Models\PriceRuleDigitalProduct;
use Illuminate\Pagination\LengthAwarePaginator;

class PriceRuleDigitalProductRepository
{
    /**
     * Create a new price rule digital product application record.
     */
    public function create(array $data): PriceRuleDigitalProduct
    {
        return PriceRuleDigitalProduct::create($data);
    }

    /**
     * Delete all digital product application records for a specific price rule.
     */
    public function deleteByPriceRuleId(int $priceRuleId): void
    {
        PriceRuleDigitalProduct::where('price_rule_id', $priceRuleId)->delete();
    }

    /**
     * Get all applications for a specific price rule with pagination.
     *
     * @return LengthAwarePaginator<int, PriceRuleDigitalProduct>
     */
    public function getByPriceRule(int $priceRuleId, int $perPage = 15): LengthAwarePaginator
    {
        return PriceRuleDigitalProduct::with('digitalProduct')
            ->where('price_rule_id', $priceRuleId)
            ->latest('applied_at')
            ->paginate($perPage);
    }

    /**
     * Get all applications for a specific digital product with pagination.
     *
     * @return LengthAwarePaginator<int, PriceRuleDigitalProduct>
     */
    public function getByDigitalProduct(int $digitalProductId, int $perPage = 15): LengthAwarePaginator
    {
        return PriceRuleDigitalProduct::with('priceRule')
            ->where('digital_product_id', $digitalProductId)
            ->latest('applied_at')
            ->paginate($perPage);
    }

    /**
     * Get all applications with optional filters and pagination.
     *
     * @return LengthAwarePaginator<int, PriceRuleDigitalProduct>
     */
    public function getFilteredPriceRuleDigitalProducts(array $filters = []): LengthAwarePaginator
    {
        $query = PriceRuleDigitalProduct::with(['digitalProduct', 'priceRule']);

        if (! empty($filters['price_rule_id'])) {
            $query->where('price_rule_id', $filters['price_rule_id']);
        }

        if (! empty($filters['digital_product_id'])) {
            $query->where('digital_product_id', $filters['digital_product_id']);
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
     * Get all digital product IDs for a specific price rule.
     *
     * @return array<int>
     */
    public function getDigitalProductIdsByPriceRuleId(int $priceRuleId): array
    {
        return PriceRuleDigitalProduct::where('price_rule_id', $priceRuleId)
            ->pluck('digital_product_id')
            ->all();
    }

    /**
     * Find a single application by ID.
     */
    public function findById(int $id): ?PriceRuleDigitalProduct
    {
        return PriceRuleDigitalProduct::with(['digitalProduct', 'priceRule'])->find($id);
    }
}
