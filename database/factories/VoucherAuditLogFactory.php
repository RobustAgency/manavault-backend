<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Voucher;
use App\Enums\VoucherAuditActions;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VoucherAuditLog>
 */
class VoucherAuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'voucher_id' => Voucher::factory(),
            'user_id' => User::factory(),
            'action' => fake()->randomElement([
                VoucherAuditActions::VIEWED->value,
                VoucherAuditActions::COPIED->value,
            ]),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Indicate that the audit log is for a viewed action.
     */
    public function viewed(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => VoucherAuditActions::VIEWED->value,
        ]);
    }

    /**
     * Indicate that the audit log is for a copied action.
     */
    public function copied(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => VoucherAuditActions::COPIED->value,
        ]);
    }
}
