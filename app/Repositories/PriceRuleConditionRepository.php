<?php

namespace App\Repositories;

use App\Models\PriceRuleCondition;

class PriceRuleConditionRepository
{
    /**
     * Create a new price rule condition.
     */
    public function create(array $data): PriceRuleCondition
    {
        return PriceRuleCondition::create($data);
    }
}
