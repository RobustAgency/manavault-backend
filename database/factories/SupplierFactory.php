<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->company();

        return [
            'name' => $name,
            'slug' => str_replace(' ', '_', strtolower($name)),
            'type' => 'internal',
            'contact_email' => $this->faker->safeEmail(),
            'contact_phone' => $this->faker->phoneNumber(),
            'status' => 'active',
        ];
    }

    /**
     * Indicate that the supplier is external.
     */
    public function external(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'external',
        ]);
    }
}
