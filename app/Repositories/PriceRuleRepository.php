<?php

namespace App\Repositories;

use App\Models\PriceRule;
use Illuminate\Pagination\LengthAwarePaginator;

class PriceRuleRepository
{
    /**
     * Get all price rules with pagination.
     *
     * @return LengthAwarePaginator<int, PriceRule>
     */
    public function getFilteredPriceRules(array $filters = []): LengthAwarePaginator
    {
        $query = PriceRule::with('conditions');

        if (! empty($filters['name'])) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }

        if (! empty($filters['match_type'])) {
            $query->where('match_type', $filters['match_type']);
        }

        if (! empty($filters['action_mode'])) {
            $query->where('action_mode', $filters['action_mode']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->latest()->paginate($perPage);
    }

    /**
     * Create a new price rule.
     */
    public function createPriceRule(array $data): PriceRule
    {
        return PriceRule::create($data);
    }

    /**
     * Update an existing price rule.
     */
    public function updatePriceRule(PriceRule $priceRule, array $data): PriceRule
    {
        $priceRule->update($data);

        return $priceRule;
    }

    /**
     * Delete a price rule.
     */
    public function deletePriceRule(PriceRule $priceRule): void
    {
        $priceRule->delete();
    }
}
