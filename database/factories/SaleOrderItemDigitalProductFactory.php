<?php

namespace Database\Factories;

use App\Models\SaleOrderItem;
use App\Models\DigitalProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SaleOrderItemDigitalProduct>
 */
class SaleOrderItemDigitalProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_order_item_id' => SaleOrderItem::factory(),
            'digital_product_id' => DigitalProduct::factory(),
            'quantity_deducted' => $this->faker->numberBetween(1, 10),
        ];
    }

    /**
     * Indicate the record belongs to a specific sale order item.
     */
    public function forSaleOrderItem(SaleOrderItem|int $saleOrderItem): static
    {
        return $this->state(fn (array $attributes) => [
            'sale_order_item_id' => $saleOrderItem instanceof SaleOrderItem ? $saleOrderItem->id : $saleOrderItem,
        ]);
    }

    /**
     * Indicate the record is for a specific digital product.
     */
    public function forDigitalProduct(DigitalProduct|int $digitalProduct): static
    {
        return $this->state(fn (array $attributes) => [
            'digital_product_id' => $digitalProduct instanceof DigitalProduct ? $digitalProduct->id : $digitalProduct,
        ]);
    }

    /**
     * Set a specific quantity deducted.
     */
    public function withQuantityDeducted(int $quantityDeducted): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_deducted' => $quantityDeducted,
        ]);
    }
}
