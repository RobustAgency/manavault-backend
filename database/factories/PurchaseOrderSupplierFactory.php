<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Enums\PurchaseOrderSupplierStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderSupplierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'supplier_id' => Supplier::factory(),
            'transaction_id' => $this->faker->uuid,
            'status' => $this->faker->randomElement([
                PurchaseOrderSupplierStatus::PROCESSING->value,
                PurchaseOrderSupplierStatus::COMPLETED->value,
            ]),
        ];
    }
}
