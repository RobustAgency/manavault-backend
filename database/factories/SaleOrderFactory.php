<?php

namespace Database\Factories;

use App\Models\SaleOrder;
use App\Enums\SaleOrder\Status;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SaleOrder>
 */
class SaleOrderFactory extends Factory
{
    /**
     * Counter for generating unique order numbers
     */
    private static int $orderCounter = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        self::$orderCounter++;

        return [
            'order_number' => 'SO-'.date('Y').'-'.str_pad(
                (string) self::$orderCounter,
                6,
                '0',
                STR_PAD_LEFT
            ),
            'source' => SaleOrder::MANASTORE,
            'total_price' => 0,
            'status' => Status::PENDING->value,
        ];
    }
}
