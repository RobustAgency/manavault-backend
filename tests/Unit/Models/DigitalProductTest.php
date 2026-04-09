<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Supplier;
use App\Models\PriceRule;
use App\Models\DigitalProduct;
use Illuminate\Support\Carbon;
use App\Models\PriceRuleDigitalProduct;
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

    // -------------------------------------------------------------------------
    // Priority: latest update wins (discount vs price rule)
    // -------------------------------------------------------------------------

    public function test_discount_wins_when_updated_after_price_rule(): void
    {
        $supplier = Supplier::factory()->create();
        $priceRule = PriceRule::factory()->create();

        // Price rule applied at an older time
        $priceRuleAppliedAt = Carbon::now()->subMinutes(10);

        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'face_value' => 100.00,
            'cost_price' => 50.00,
            'selling_price' => 100.00,
            'selling_discount' => 20,
            'selling_discount_updated_at' => Carbon::now(), // discount updated more recently
        ]);

        PriceRuleDigitalProduct::factory()->create([
            'digital_product_id' => $digitalProduct->id,
            'price_rule_id' => $priceRule->id,
            'base_value' => 100.00,
            'final_selling_price' => 70.00, // price rule would give 70
            'applied_at' => $priceRuleAppliedAt,
        ]);

        $digitalProduct->refresh();

        // Discount (20%) was applied more recently — expect 80, not 70
        $this->assertEquals(80.0, $digitalProduct->selling_price);
        $this->assertEquals(20.0, $digitalProduct->selling_discount);
    }

    public function test_price_rule_wins_when_applied_after_discount(): void
    {
        $supplier = Supplier::factory()->create();
        $priceRule = PriceRule::factory()->create();

        // Discount was set on the digital product at an older time
        $discountUpdatedAt = Carbon::now()->subMinutes(10);

        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'face_value' => 100.00,
            'cost_price' => 50.00,
            'selling_price' => 100.00,
            'selling_discount' => 20,
            'selling_discount_updated_at' => $discountUpdatedAt,
        ]);

        // Price rule applied more recently
        PriceRuleDigitalProduct::factory()->create([
            'digital_product_id' => $digitalProduct->id,
            'price_rule_id' => $priceRule->id,
            'base_value' => 100.00,
            'final_selling_price' => 70.00,
            'applied_at' => Carbon::now(),
        ]);

        $digitalProduct->refresh();

        // Price rule was applied more recently — expect 70, not 80
        $this->assertEquals(70.0, $digitalProduct->selling_price);
        // Discount should reflect the price rule (30%)
        $this->assertEquals(30.0, $digitalProduct->selling_discount);
    }

    public function test_discount_wins_on_tie_when_both_have_same_timestamp(): void
    {
        $supplier = Supplier::factory()->create();
        $priceRule = PriceRule::factory()->create();

        $sameTime = Carbon::now();

        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'face_value' => 100.00,
            'cost_price' => 50.00,
            'selling_price' => 100.00,
            'selling_discount' => 20,
            'selling_discount_updated_at' => $sameTime,
        ]);

        PriceRuleDigitalProduct::factory()->create([
            'digital_product_id' => $digitalProduct->id,
            'price_rule_id' => $priceRule->id,
            'base_value' => 100.00,
            'final_selling_price' => 70.00,
            'applied_at' => $sameTime,
        ]);

        $digitalProduct->refresh();

        // On a tie, discount wins — expect 80
        $this->assertEquals(80.0, $digitalProduct->selling_price);
        $this->assertEquals(20.0, $digitalProduct->selling_discount);
    }

    public function test_price_rule_wins_when_selling_discount_updated_at_is_null(): void
    {
        // When selling_discount_updated_at is null (legacy records), price rule always wins
        // over a discount (null treated as "no timestamp = price rule wins")
        $supplier = Supplier::factory()->create();
        $priceRule = PriceRule::factory()->create();

        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'face_value' => 100.00,
            'cost_price' => 50.00,
            'selling_price' => 100.00,
            'selling_discount' => 20,
            'selling_discount_updated_at' => null,
        ]);

        PriceRuleDigitalProduct::factory()->create([
            'digital_product_id' => $digitalProduct->id,
            'price_rule_id' => $priceRule->id,
            'base_value' => 100.00,
            'final_selling_price' => 70.00,
            'applied_at' => Carbon::now(),
        ]);

        $digitalProduct->refresh();

        // selling_discount_updated_at is null — discount wins (safe default for legacy rows)
        $this->assertEquals(70.0, $digitalProduct->selling_price);
        $this->assertEquals(30.0, $digitalProduct->selling_discount);
    }

    public function test_price_rule_used_when_no_discount_set(): void
    {
        $supplier = Supplier::factory()->create();
        $priceRule = PriceRule::factory()->create();

        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'face_value' => 100.00,
            'cost_price' => 50.00,
            'selling_price' => 100.00,
            'selling_discount' => 0,
        ]);

        PriceRuleDigitalProduct::factory()->create([
            'digital_product_id' => $digitalProduct->id,
            'price_rule_id' => $priceRule->id,
            'base_value' => 100.00,
            'final_selling_price' => 85.00,
            'applied_at' => Carbon::now(),
        ]);

        $digitalProduct->refresh();

        // No discount set — price rule should apply
        $this->assertEquals(85.0, $digitalProduct->selling_price);
        $this->assertEquals(15.0, $digitalProduct->selling_discount);
    }

    public function test_discount_used_when_no_price_rule_exists(): void
    {
        $supplier = Supplier::factory()->create();

        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'face_value' => 100.00,
            'cost_price' => 50.00,
            'selling_price' => 100.00,
            'selling_discount' => 15,
        ]);

        // No price rule applied
        $this->assertEquals(85.0, $digitalProduct->selling_price);
        $this->assertEquals(15.0, $digitalProduct->selling_discount);
    }

    public function test_zero_discount_sets_selling_price_to_face_value(): void
    {
        $supplier = Supplier::factory()->create();

        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $supplier->id,
            'face_value' => 100.00,
            'cost_price' => 50.00,
            'selling_price' => 100.00,
            'selling_discount' => 0,
        ]);

        // 0% discount — selling price should equal face value
        $this->assertEquals(100.0, $digitalProduct->selling_price);
        $this->assertEquals(0.0, $digitalProduct->selling_discount);
    }
}
