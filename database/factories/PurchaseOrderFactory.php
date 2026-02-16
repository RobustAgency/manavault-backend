<?php

namespace Database\Factories;

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
            'total_price' => $this->faker->randomFloat(2, 50, 1000),
            'order_number' => $this->faker->unique()->regexify('PO-[0-9]{8}'),
            'status' => $this->faker->randomElement(['pending', 'processing', 'completed', 'cancelled']),
        ];
    }

    /**
     * Indicate the purchase order is for a specific supplier.
     */
    public function forSupplier(Supplier|int $supplier): static
    {
        return $this->state(fn (array $attributes) => [
            'supplier_id' => $supplier instanceof Supplier ? $supplier->id : $supplier,
        ]);
    }

    /**
     * Indicate that the purchase order has a transaction ID (EZ Cards).
     */
    public function withTransactionId(?string $transactionId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'transaction_id' => $transactionId ?? 'TXN-'.time().rand(100, 999),
        ]);
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
     * Indicate that the purchase order is processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
        ]);
    }

    /**
     * Indicate that the purchase order is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
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
     * Set a specific total price.
     */
    public function withTotalPrice(float $totalPrice): static
    {
        return $this->state(fn (array $attributes) => [
            'total_price' => $totalPrice,
        ]);
    }
}
