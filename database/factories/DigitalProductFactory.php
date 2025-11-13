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
            'brand' => $this->faker->company(),
            'description' => $this->faker->sentence(),
            'tags' => $this->faker->randomElements(['gaming', 'entertainment', 'gift card', 'digital', 'popular'], $this->faker->numberBetween(1, 3)),
            'image' => $this->faker->imageUrl(640, 480, 'products', true),
            'cost_price' => $costPrice,
            'status' => $this->faker->randomElement(['active', 'inactive']),
            'regions' => $this->faker->randomElements(['US', 'CA', 'UK', 'EU', 'AU'], $this->faker->numberBetween(1, 3)),
            'metadata' => [
                'external_id' => $this->faker->uuid(),
                'category' => $this->faker->randomElement(['Gift Cards', 'Gaming', 'Entertainment']),
                'min_value' => $this->faker->numberBetween(5, 25),
                'max_value' => $this->faker->numberBetween(100, 500),
            ],
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
