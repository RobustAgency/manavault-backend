<?php

namespace Database\Factories;

use App\Models\DigitalProduct;
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
        $plainCode = strtoupper($this->faker->bothify('??##-??##-??##-??##'));

        return [
            'code' => $plainCode,
            'code_hash' => hash_hmac('sha256', $plainCode, base64_decode(config('services.voucher.encryption_key'))),
            'digital_product_id' => null,
            'purchase_order_id' => PurchaseOrder::factory(),
            'serial_number' => $this->faker->bothify('SN-######'),
            'status' => 'COMPLETED',
            'pin_code' => $this->faker->numerify('####'),
            'stock_id' => $this->faker->numberBetween(1, 1000),
            'expires_at' => $this->faker->optional()->dateTimeBetween('now', '+3 years'),
        ];
    }

    public function withDigitalProduct(DigitalProduct|int $product): static
    {
        return $this->state(fn () => [
            'digital_product_id' => $product instanceof DigitalProduct ? $product->id : $product,
        ]);
    }

    /**
     * Indicate that the voucher is still processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'PROCESSING',
            'code' => null,
            'code_hash' => null,
            'pin_code' => null,
        ]);
    }
}
