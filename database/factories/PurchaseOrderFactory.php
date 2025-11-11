<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'supplier_id' => Supplier::factory(),
            'purchase_price' => $this->faker->randomFloat(2, 10, 500),
            'quantity' => $this->faker->numberBetween(1, 100),
            'order_number' => $this->faker->uuid(),
        ];
    }

    /**
     * Indicate that the purchase order is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the purchase order is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
        ]);
    }

    /**
     * Indicate that the purchase order is received.
     */
    public function received(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'received',
        ]);
    }

    /**
     * Indicate that the purchase order is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * Create a purchase order with a specific product.
     */
    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
        ]);
    }

    /**
     * Create a purchase order with a specific supplier.
     */
    public function forSupplier(Supplier $supplier): static
    {
        return $this->state(fn (array $attributes) => [
            'supplier_id' => $supplier->id,
        ]);
    }

    /**
     * Create a purchase order with a large quantity.
     */
    public function largeQuantity(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => $this->faker->numberBetween(100, 1000),
        ]);
    }

    /**
     * Create a purchase order with a high purchase price.
     */
    public function expensive(): static
    {
        return $this->state(fn (array $attributes) => [
            'purchase_price' => $this->faker->randomFloat(2, 500, 2000),
        ]);
    }
}
