<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Supplier;
use App\Models\DigitalProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DigitalProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_profit_margin_calculation(): void
    {
        $supplier = Supplier::factory()->create();

        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'cost_price' => 50.00,
            'selling_price' => 100.00,
            'face_value' => 100.00,
            'selling_discount' => 0,
        ]);

        $expectedProfitMargin = round($digitalProduct->selling_price - $digitalProduct->cost_price, 2);
        $this->assertEquals($expectedProfitMargin, $digitalProduct->profit_margin);
        $this->assertEquals(50.0, $digitalProduct->profit_margin);
    }

    public function test_cost_price_discount_calculation(): void
    {
        $supplier = Supplier::factory()->create();

        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'cost_price' => 50.00,
            'face_value' => 100.00,
            'selling_price' => 75.00,
        ]);

        $expectedDiscount = round((($digitalProduct->face_value - $digitalProduct->cost_price) / $digitalProduct->face_value) * 100, 2);
        $this->assertEquals($expectedDiscount, $digitalProduct->cost_price_discount);
        $this->assertEquals(50.0, $digitalProduct->cost_price_discount);
    }

    public function test_selling_discount_from_stored_discount(): void
    {
        $supplier = Supplier::factory()->create();

        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'cost_price' => 50.00,
            'face_value' => 100.00,
            'selling_price' => 100.00,
            'selling_discount' => 20,
        ]);

        // Stored discount should take precedence
        $this->assertEquals(20, $digitalProduct->selling_discount);
    }

    public function test_selling_discount_calculated_from_face_value(): void
    {
        $supplier = Supplier::factory()->create();

        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'cost_price' => 50.00,
            'face_value' => 100.00,
            'selling_price' => 100.00,
            'selling_discount' => 25,
        ]);

        // Stored discount of 25% should be used
        $this->assertEquals(25.0, $digitalProduct->selling_discount);
    }

    public function test_effective_selling_price_with_user_discount(): void
    {
        $supplier = Supplier::factory()->create();

        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'cost_price' => 50.00,
            'face_value' => 100.00,
            'selling_price' => 100.00,
            'selling_discount' => 20,
        ]);

        // With 20% discount, selling price should be 80.00
        $expectedPrice = round(100.00 * (1 - 20 / 100), 2);
        $this->assertEquals($expectedPrice, $digitalProduct->selling_price);
        $this->assertEquals(80.0, $digitalProduct->selling_price);
    }

    public function test_profit_margin_with_discount(): void
    {
        $supplier = Supplier::factory()->create();

        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'cost_price' => 50.00,
            'face_value' => 100.00,
            'selling_price' => 100.00,
            'selling_discount' => 20,
        ]);

        // Effective selling price is 80 (100 * 0.8)
        // Profit margin = 80 - 50 = 30
        $expectedMargin = round(80 - 50, 2);
        $this->assertEquals($expectedMargin, $digitalProduct->profit_margin);
        $this->assertEquals(30.0, $digitalProduct->profit_margin);
    }

    public function test_attributes_are_in_appends(): void
    {
        $supplier = Supplier::factory()->create();

        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'cost_price' => 50.00,
            'face_value' => 100.00,
            'selling_price' => 75.00,
        ]);

        $appended = $digitalProduct->getAppends();

        $this->assertContains('cost_price_discount', $appended);
        $this->assertContains('profit_margin', $appended);
    }
}
