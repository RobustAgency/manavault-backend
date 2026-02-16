<?php

namespace Database\Factories;

use App\Enums\PriceRule\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PriceRule>
 */
class PriceRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'match_type' => $this->faker->randomElement(['all', 'any']),
            'action_operator' => $this->faker->randomElement(['add', 'subtract', 'multiply']),
            'action_mode' => $this->faker->randomElement(['percentage', 'fixed']),
            'action_value' => $this->faker->randomFloat(2, 1, 100),
            'status' => $this->faker->randomElement(Status::cases())->value,
        ];
    }
}
