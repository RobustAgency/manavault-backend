<?php

namespace Database\Factories;

use App\Enums\Product\Lifecycle;
use App\Models\Supplier;
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
        $purchasePrice = $this->faker->randomFloat(2, 5, 100);
        $markup = $this->faker->randomFloat(2, 1.2, 2.0); // 20% to 100% markup

        return [
            'supplier_id' => Supplier::factory(),
            'name' => $this->faker->words(3, true),
            'sku' => $this->faker->unique()->bothify('SKU-####-??##'),
            'description' => $this->faker->sentence(),
            'purchase_price' => $purchasePrice,
            'selling_price' => round($purchasePrice * $markup, 2),
            'status' => $this->faker->randomElement(array_map(fn($c) => $c->value, Lifecycle::cases())),
        ];
    }

    /**
     * Indicate that the product is for a specific supplier.
     */
    public function forSupplier(Supplier|int $supplier): static
    {
        return $this->state(fn(array $attributes) => [
            'supplier_id' => $supplier instanceof Supplier ? $supplier->id : $supplier,
        ]);
    }

    /**
     * Indicate that the product is active.
     */
    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the product is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}
