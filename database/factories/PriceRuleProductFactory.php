<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\PriceRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PriceRuleProduct>
 */
class PriceRuleProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $baseValue = $this->faker->randomFloat(2, 5, 200);
        $actionValue = $this->faker->randomFloat(2, 1, 50);
        $finalSellingPrice = $this->faker->randomFloat(2, 5, 250);

        return [
            'product_id' => Product::factory(),
            'price_rule_id' => PriceRule::factory(),
            'original_selling_price' => $this->faker->randomFloat(2, 5, 200),
            'base_value' => $baseValue,
            'action_mode' => $this->faker->randomElement(['percentage', 'fixed']),
            'action_operator' => $this->faker->randomElement(['add', 'subtract']),
            'action_value' => $actionValue,
            'calculated_price' => $finalSellingPrice,
            'final_selling_price' => $finalSellingPrice,
            'applied_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }
}
