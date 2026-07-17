<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    private static int $counter = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        self::$counter++;

        return [
            'external_id' => 'CUST-'.self::$counter,
            'name' => $this->faker->name(),
            'email' => $this->faker->safeEmail(),
            'company_name' => $this->faker->company(),
            'company_email' => $this->faker->companyEmail(),
        ];
    }
}
