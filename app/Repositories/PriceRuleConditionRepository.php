<?php

namespace App\Repositories;

use App\Models\PriceRule;
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

    /**
     * Delete conditions by price rule.
     */
    public function deleteConditionsByPriceRule(PriceRule $priceRule): void
    {
        PriceRuleCondition::where('price_rule_id', $priceRule->id)->delete();
    }
}
