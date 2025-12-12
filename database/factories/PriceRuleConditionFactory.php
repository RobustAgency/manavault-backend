<?php

namespace Database\Factories;

use App\Models\PriceRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PriceRuleCondition>
 */
class PriceRuleConditionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'price_rule_id' => PriceRule::factory(),
            'field' => $this->faker->randomElement(['brand_id', 'selling_price', 'name', 'status', 'sku']),
            'operator' => $this->faker->randomElement(['=', '!=', '>', '<', '>=', '<=', 'contains']),
            'value' => $this->faker->word(),
        ];
    }
}
