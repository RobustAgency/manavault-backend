<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\SaleOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SaleOrderItem>
 */
class SaleOrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 5);
        $unitPrice = $this->faker->randomFloat(2, 10, 500);
        $subtotal = $quantity * $unitPrice;

        return [
            'sale_order_id' => SaleOrder::factory(),
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $subtotal,
        ];
    }

    /**
     * Indicate the item belongs to a specific sale order.
     */
    public function forSaleOrder(SaleOrder|int $saleOrder): static
    {
        return $this->state(fn (array $attributes) => [
            'sale_order_id' => $saleOrder instanceof SaleOrder ? $saleOrder->id : $saleOrder,
        ]);
    }

    /**
     * Indicate the item is for a specific product.
     */
    public function forProduct(Product|int $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product instanceof Product ? $product->id : $product,
        ]);
    }

    /**
     * Set a specific quantity for the item.
     */
    public function withQuantity(int $quantity): static
    {
        return $this->state(function (array $attributes) use ($quantity) {
            $subtotal = $quantity * $attributes['unit_price'];

            return [
                'quantity' => $quantity,
                'subtotal' => $subtotal,
            ];
        });
    }

    /**
     * Set a specific unit price for the item.
     */
    public function withUnitPrice(float $unitPrice): static
    {
        return $this->state(function (array $attributes) use ($unitPrice) {
            $subtotal = $attributes['quantity'] * $unitPrice;

            return [
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
            ];
        });
    }
}
