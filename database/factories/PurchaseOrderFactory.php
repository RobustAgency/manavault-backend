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
        $quantity = $this->faker->numberBetween(1, 50);
        $product = Product::factory()->create();
        $supplier = Supplier::factory()->create();
        $unitPrice = $this->faker->randomFloat(2, 5, 100);

        return [
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'total_price' => $unitPrice * $quantity,
            'quantity' => $quantity,
            'order_number' => $this->faker->uuid(),
            'transaction_id' => null,
            'voucher_codes_processed' => false,
            'voucher_codes_processed_at' => null,
        ];
    }

    /**
     * Indicate that the purchase order has a transaction ID (EZ Cards).
     */
    public function withTransactionId(): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_id' => 'TXN-'.time().rand(100, 999),
        ]);
    }
}
