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
        $query = PriceRule::query();

        if (! empty($filters['name'])) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }

        if (! empty($filters['match_type'])) {
            $query->where('match_type', $filters['match_type']);
        }

        if (! empty($filters['action_operator'])) {
            $query->where('action_operator', $filters['action_operator']);
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
}
