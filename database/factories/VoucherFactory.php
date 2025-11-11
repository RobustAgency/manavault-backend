<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Voucher>
 */
class VoucherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->bothify('??##-??##-??##-??##')),
            'purchase_order_id' => PurchaseOrder::factory(),
            'serial_number' => $this->faker->bothify('SN-######'),
            'status' => 'COMPLETED',
            'pin_code' => $this->faker->numerify('####'),
            'stock_id' => $this->faker->numberBetween(1, 1000),
        ];
    }

    /**
     * Indicate that the voucher is still processing.
     */
    public function processing(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'PROCESSING',
            'code' => null,
            'pin_code' => null,
        ]);
    }
}
