<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DigitalProduct>
 */
class DigitalProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $costPrice = $this->faker->randomFloat(2, 5, 100);

        return [
            'supplier_id' => Supplier::factory(),
            'name' => $this->faker->words(3, true),
            'sku' => $this->faker->unique()->regexify('[A-Z]{3}-[0-9]{5}'),
            'brand' => $this->faker->company(),
            'description' => $this->faker->sentence(),
            'cost_price' => $costPrice,
            'metadata' => [
                'external_id' => $this->faker->uuid(),
                'category' => $this->faker->randomElement(['Gift Cards', 'Gaming', 'Entertainment']),
                'min_value' => $this->faker->numberBetween(5, 25),
                'max_value' => $this->faker->numberBetween(100, 500),
            ],
            'last_synced_at' => null,
        ];
    }

    /**
     * Indicate that the digital product is for a specific supplier.
     */
    public function forSupplier(Supplier|int $supplier): static
    {
        return $this->state(fn (array $attributes) => [
            'supplier_id' => $supplier instanceof Supplier ? $supplier->id : $supplier,
        ]);
    }

    /**
     * Indicate that the digital product is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the digital product is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}
