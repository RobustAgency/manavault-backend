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
            'code' => $this->generateVoucherCode(),
            'purchase_order_id' => PurchaseOrder::factory(),
        ];
    }

    /**
     * Generate a unique voucher code
     */
    private function generateVoucherCode(): string
    {
        return 'VCH-' . strtoupper($this->faker->bothify('??##-####-??##'));
    }
}
