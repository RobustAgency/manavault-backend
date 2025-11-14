<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 10);
        $unitCost = $this->faker->randomFloat(2, 5, 100);
        $subtotal = $quantity * $unitCost;

        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'digital_product_id' => DigitalProduct::factory(),
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'subtotal' => $subtotal,
        ];
    }

    /**
     * Indicate the item is for a specific purchase order.
     */
    public function forPurchaseOrder(PurchaseOrder|int $purchaseOrder): static
    {
        return $this->state(fn (array $attributes) => [
            'purchase_order_id' => $purchaseOrder instanceof PurchaseOrder ? $purchaseOrder->id : $purchaseOrder,
        ]);
    }

    /**
     * Indicate the item is for a specific digital product.
     */
    public function forDigitalProduct(DigitalProduct|int $digitalProduct): static
    {
        return $this->state(fn (array $attributes) => [
            'digital_product_id' => $digitalProduct instanceof DigitalProduct ? $digitalProduct->id : $digitalProduct,
        ]);
    }

    /**
     * Set a specific quantity for the item.
     */
    public function withQuantity(int $quantity): static
    {
        return $this->state(function (array $attributes) use ($quantity) {
            $subtotal = $quantity * $attributes['unit_cost'];

            return [
                'quantity' => $quantity,
                'subtotal' => $subtotal,
            ];
        });
    }

    /**
     * Set a specific unit cost for the item.
     */
    public function withUnitCost(float $unitCost): static
    {
        return $this->state(function (array $attributes) use ($unitCost) {
            $subtotal = $attributes['quantity'] * $unitCost;

            return [
                'unit_cost' => $unitCost,
                'subtotal' => $subtotal,
            ];
        });
    }
}
