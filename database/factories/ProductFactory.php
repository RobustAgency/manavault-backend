<?php

namespace Database\Factories;

use App\Enums\Product\Lifecycle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sellingPrice = $this->faker->randomFloat(2, 10, 200);

        return [
            'name' => $this->faker->words(3, true),
            'sku' => $this->faker->unique()->bothify('SKU-####-??##'),
            'brand' => $this->faker->company(),
            'description' => $this->faker->sentence(),
            'short_description' => $this->faker->sentence(10),
            'long_description' => $this->faker->paragraph(3),
            'tags' => $this->faker->randomElements(['gaming', 'entertainment', 'gift card', 'digital', 'popular'], $this->faker->numberBetween(1, 3)),
            'image' => $this->faker->imageUrl(640, 480, 'products', true),
            'selling_price' => $sellingPrice,
            'status' => $this->faker->randomElement(array_map(fn ($c) => $c->value, Lifecycle::cases())),
            'regions' => $this->faker->randomElements(['US', 'CA', 'UK', 'EU', 'AU'], $this->faker->numberBetween(1, 3)),
        ];
    }

    /**
     * Indicate that the product is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the product is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}
