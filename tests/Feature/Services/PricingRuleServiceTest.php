<?php

namespace Tests\Feature\Services;

use Tests\TestCase;
use App\Models\Brand;
use App\Models\Product;
use App\Models\PriceRule;
use App\Enums\PriceRule\Status;
use App\Enums\PriceRule\ActionMode;
use App\Services\PricingRuleService;
use App\Enums\PriceRule\ActionOperator;
use App\Enums\PriceRuleCondition\Operator;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PricingRuleServiceTest extends TestCase
{
    use RefreshDatabase;

    private PricingRuleService $service;

    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PricingRuleService::class);
        $this->brand = Brand::factory()->create();
    }

    public function test_create_price_rule_with_conditions(): void
    {
        $product = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'face_value' => 100.00,
            'selling_price' => 100.00,
        ]);

        $data = [
            'name' => 'Test Price Rule',
            'description' => 'Apply discount to brand',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand_id',
                    'operator' => Operator::EQUAL->value,
                    'value' => $this->brand->id,
                ],
            ],
        ];

        $this->service->createPriceRuleWithConditions($data);

        $this->assertDatabaseHas('price_rules', [
            'name' => 'Test Price Rule',
            'match_type' => 'all',
            'action_mode' => ActionMode::PERCENTAGE->value,
        ]);

        $this->assertDatabaseHas('price_rule_conditions', [
            'field' => 'brand_id',
            'operator' => Operator::EQUAL->value,
        ]);

        $product->refresh();
        // Price should be reduced by 10%: 100 - (100 * 0.10) = 90
        $this->assertDatabaseHas('price_rule_product', [
            'product_id' => $product->id,
            'final_selling_price' => 90.00,
        ]);
    }

    public function test_create_price_rule_with_percentage_addition(): void
    {
        $product = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'face_value' => 100.00,
            'selling_price' => 100.00,
        ]);

        $data = [
            'name' => 'Markup Rule',
            'description' => 'Add markup to products',
            'match_type' => 'all',
            'action_operator' => ActionOperator::ADDITION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 20,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand_id',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $this->brand->id,
                ],
            ],
        ];

        $this->service->createPriceRuleWithConditions($data);

        $product->refresh();
        // Price should be increased by 20%: 100 + (100 * 0.20) = 120
        $this->assertDatabaseHas('price_rule_product', [
            'product_id' => $product->id,
            'final_selling_price' => 120.00,
        ]);
    }

    public function test_create_price_rule_with_absolute_value(): void
    {
        $product = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'face_value' => 100.00,
            'selling_price' => 100.00,
        ]);

        $data = [
            'name' => 'Fixed Discount Rule',
            'description' => 'Apply fixed discount',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::ABSOLUTE->value,
            'action_value' => 15,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand_id',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $this->brand->id,
                ],
            ],
        ];

        $this->service->createPriceRuleWithConditions($data);

        $product->refresh();
        // Price should be reduced by fixed amount: 100 - 15 = 85
        $this->assertDatabaseHas('price_rule_product', [
            'product_id' => $product->id,
            'final_selling_price' => 85.00,
        ]);
    }

    public function test_create_price_rule_with_multiple_conditions_all(): void
    {
        $brand2 = Brand::factory()->create();
        $product1 = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'selling_price' => 50.00,
            'face_value' => 50.00,
        ]);
        $product2 = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'selling_price' => 150.00,
            'face_value' => 150.00,
        ]);
        $product3 = Product::factory()->create([
            'brand_id' => $brand2->id,
            'selling_price' => 150.00,
            'face_value' => 150.00,
        ]);

        $data = [
            'name' => 'Premium Brand Discount',
            'description' => 'Discount for premium products of specific brand',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand_id',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $this->brand->id,
                ],
                [
                    'field' => 'selling_price',
                    'operator' => Operator::GREATER_THAN_OR_EQUAL->value,
                    'value' => '100',
                ],
            ],
        ];

        $this->service->createPriceRuleWithConditions($data);

        $product1->refresh();
        $product2->refresh();
        $product3->refresh();

        // Only product2 should be discounted (brand matches AND price >= 100)
        $this->assertDatabaseMissing('price_rule_product', ['product_id' => $product1->id]); // Not changed
        $this->assertDatabaseHas('price_rule_product', [
            'product_id' => $product2->id,
            'final_selling_price' => 135.00, // 150 - (150 * 0.10) = 135
        ]);
        $this->assertDatabaseMissing('price_rule_product', ['product_id' => $product3->id]); // Not changed (different brand)
    }

    public function test_create_price_rule_with_multiple_conditions_any(): void
    {
        $brand2 = Brand::factory()->create();
        $product1 = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'selling_price' => 50.00,
            'face_value' => 50.00,
        ]);
        $product2 = Product::factory()->create([
            'brand_id' => $brand2->id,
            'selling_price' => 50.00,
            'face_value' => 50.00,
        ]);
        $product3 = Product::factory()->create([
            'brand_id' => $brand2->id,
            'selling_price' => 150.00,
            'face_value' => 150.00,
        ]);

        $data = [
            'name' => 'Special Offer',
            'description' => 'Discount for specific brand OR high-priced items',
            'match_type' => 'any',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 5,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand_id',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $this->brand->id,
                ],
                [
                    'field' => 'selling_price',
                    'operator' => Operator::GREATER_THAN_OR_EQUAL->value,
                    'value' => '100',
                ],
            ],
        ];

        $this->service->createPriceRuleWithConditions($data);

        $product1->refresh();
        $product2->refresh();
        $product3->refresh();

        // product1 matches brand condition, product3 matches price condition
        $this->assertDatabaseHas('price_rule_product', [
            'product_id' => $product1->id,
            'final_selling_price' => 47.50, // 50 - (50 * 0.05) = 47.5
        ]);
        $this->assertDatabaseMissing('price_rule_product', ['product_id' => $product2->id]); // Not changed
        $this->assertDatabaseHas('price_rule_product', [
            'product_id' => $product3->id,
            'final_selling_price' => 142.50, // 150 - (150 * 0.05) = 142.5
        ]);
    }

    public function test_update_price_rule_with_conditions(): void
    {
        $priceRule = PriceRule::factory()->create([
            'name' => 'Old Rule',
            'action_value' => 5,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_operator' => ActionOperator::SUBTRACTION->value,
        ]);

        $product = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'face_value' => 100.00,
            'selling_price' => 100.00,
        ]);

        $updateData = [
            'name' => 'Updated Rule',
            'action_value' => 15,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'conditions' => [
                [
                    'field' => 'brand_id',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $this->brand->id,
                ],
            ],
        ];

        $this->service->updatePriceRuleWithConditions($priceRule, $updateData);

        $this->assertDatabaseHas('price_rules', [
            'id' => $priceRule->id,
            'name' => 'Updated Rule',
            'action_value' => 15,
        ]);

        $product->refresh();
        // Price should be reduced by 15%: 100 - (100 * 0.15) = 85
        $this->assertDatabaseHas('price_rule_product', [
            'product_id' => $product->id,
            'price_rule_id' => $priceRule->id,
            'final_selling_price' => 85.00,
        ]);
    }

    public function test_update_does_not_stack_price_rule_product_records(): void
    {
        $priceRule = PriceRule::factory()->create([
            'name' => 'Rule',
            'match_type' => 'all',
            'action_value' => 10,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'status' => Status::ACTIVE->value,
        ]);

        $product = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'face_value' => 100.00,
            'selling_price' => 100.00,
        ]);

        $updateData = [
            'name' => 'Rule',
            'match_type' => 'all',
            'action_value' => 20,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand_id',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $this->brand->id,
                ],
            ],
        ];

        // Call update twice to simulate multiple saves
        $this->service->updatePriceRuleWithConditions($priceRule, $updateData);
        $this->service->updatePriceRuleWithConditions($priceRule, $updateData);

        // There must be exactly 1 record per product, not stacked duplicates
        $count = \App\Models\PriceRuleProduct::where('price_rule_id', $priceRule->id)
            ->where('product_id', $product->id)
            ->count();

        $this->assertEquals(1, $count);

        // And the price should reflect the latest update: 100 - 20% = 80
        $this->assertDatabaseHas('price_rule_product', [
            'product_id' => $product->id,
            'price_rule_id' => $priceRule->id,
            'final_selling_price' => 80.00,
        ]);
    }

    public function test_update_clears_stale_products_when_conditions_change(): void
    {
        $brand2 = Brand::factory()->create();

        $product1 = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'face_value' => 100.00,
            'selling_price' => 100.00,
        ]);
        $product2 = Product::factory()->create([
            'brand_id' => $brand2->id,
            'face_value' => 100.00,
            'selling_price' => 100.00,
        ]);

        $initialData = [
            'name' => 'Rule',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand_id',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $this->brand->id,
                ],
            ],
        ];

        $priceRule = \App\Models\PriceRule::factory()->create([
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => Status::ACTIVE->value,
        ]);

        $this->service->updatePriceRuleWithConditions($priceRule, $initialData);

        // product1 should be in price_rule_product, product2 should not
        $this->assertDatabaseHas('price_rule_product', ['product_id' => $product1->id, 'price_rule_id' => $priceRule->id]);
        $this->assertDatabaseMissing('price_rule_product', ['product_id' => $product2->id, 'price_rule_id' => $priceRule->id]);

        // Now update to target brand2 instead
        $updatedData = [
            'name' => 'Rule',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand_id',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $brand2->id,
                ],
            ],
        ];

        $this->service->updatePriceRuleWithConditions($priceRule, $updatedData);

        // product1's old record must be gone, product2's new record must exist
        $this->assertDatabaseMissing('price_rule_product', ['product_id' => $product1->id, 'price_rule_id' => $priceRule->id]);
        $this->assertDatabaseHas('price_rule_product', ['product_id' => $product2->id, 'price_rule_id' => $priceRule->id]);
    }

    public function test_update_price_rule_replaces_conditions(): void
    {
        $priceRule = PriceRule::factory()->create();
        $brand2 = Brand::factory()->create();

        $product1 = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'face_value' => 100.00,
            'selling_price' => 100.00,
        ]);
        $product2 = Product::factory()->create([
            'brand_id' => $brand2->id,
            'face_value' => 100.00,
            'selling_price' => 100.00,
        ]);

        // Create initial conditions
        $initialData = [
            'name' => 'Rule',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand_id',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $this->brand->id,
                ],
            ],
        ];

        $this->service->createPriceRuleWithConditions($initialData);

        // Update with new conditions
        $updateData = [
            'name' => 'Updated Rule',
            'action_value' => 20,
            'conditions' => [
                [
                    'field' => 'brand_id',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $brand2->id,
                ],
            ],
        ];

        $this->service->updatePriceRuleWithConditions($priceRule, $updateData);

        // Old condition should be deleted, new one should exist
        $this->assertDatabaseMissing('price_rule_conditions', [
            'price_rule_id' => $priceRule->id,
            'field' => 'brand_id',
            'value' => (string) $this->brand->id,
        ]);

        $this->assertDatabaseHas('price_rule_conditions', [
            'price_rule_id' => $priceRule->id,
            'field' => 'brand_id',
            'value' => (string) $brand2->id,
        ]);
    }

    public function test_selling_price_cannot_be_negative(): void
    {
        $product = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'face_value' => 50.00,
            'selling_price' => 50.00,
        ]);

        $data = [
            'name' => 'Extreme Discount',
            'description' => 'Discount larger than price',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::ABSOLUTE->value,
            'action_value' => 100, // More than the product price
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand_id',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $this->brand->id,
                ],
            ],
        ];

        $this->service->createPriceRuleWithConditions($data);

        $product->refresh();
        // Price should be 0 minimum, not negative
        $this->assertDatabaseHas('price_rule_product', [
            'product_id' => $product->id,
            'final_selling_price' => 0.00,
        ]);
    }

    public function test_create_rule_with_no_matching_products(): void
    {
        $other_brand = Brand::factory()->create();
        Product::factory()->create([
            'brand_id' => $other_brand->id,
            'face_value' => 100.00,
            'selling_price' => 100.00,
        ]);

        $data = [
            'name' => 'No Match Rule',
            'description' => 'Rule that matches no products',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand_id',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $this->brand->id,
                ],
            ],
        ];

        // Should not throw error
        $this->service->createPriceRuleWithConditions($data);

        $this->assertDatabaseHas('price_rules', [
            'name' => 'No Match Rule',
        ]);
    }

    // -------------------------------------------------------------------------
    // Product::getSellingPriceAttribute – automation applied tests
    // -------------------------------------------------------------------------

    public function test_selling_price_accessor_returns_final_selling_price_from_active_rule(): void
    {
        $product = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'face_value' => 100.00,
            'selling_price' => 100.00,
        ]);

        $data = [
            'name' => 'Accessor Test Rule',
            'description' => 'Test accessor returns discounted price',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand_id',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $this->brand->id,
                ],
            ],
        ];

        $this->service->createPriceRuleWithConditions($data);

        $product->refresh();

        // Accessor must return 90.00 (100 - 10%), not the raw 100.00
        $this->assertEquals(90.00, $product->selling_price);
    }

    public function test_selling_price_accessor_returns_original_when_no_active_rule(): void
    {
        $product = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'face_value' => 100.00,
            'selling_price' => 75.00,
        ]);

        // No PriceRuleProduct records exist for this product
        $this->assertEquals(75.00, $product->selling_price);
    }

    public function test_selling_price_accessor_returns_original_when_rule_is_inactive(): void
    {
        $product = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'face_value' => 100.00,
            'selling_price' => 100.00,
        ]);

        $inactiveRule = PriceRule::factory()->create([
            'status' => Status::INACTIVE->value,
        ]);

        \App\Models\PriceRuleProduct::factory()->create([
            'product_id' => $product->id,
            'price_rule_id' => $inactiveRule->id,
            'final_selling_price' => 50.00,
        ]);

        $product->refresh();

        // Inactive rule must be ignored; original price returned
        $this->assertEquals(100.00, $product->selling_price);
    }

    public function test_selling_price_accessor_returns_latest_when_multiple_active_rules_applied(): void
    {
        $product = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'face_value' => 100.00,
            'selling_price' => 100.00,
        ]);

        $activeRule = PriceRule::factory()->create(['status' => Status::ACTIVE->value]);

        // Older record
        \App\Models\PriceRuleProduct::factory()->create([
            'product_id' => $product->id,
            'price_rule_id' => $activeRule->id,
            'final_selling_price' => 80.00,
            'updated_at' => now()->subMinutes(10),
        ]);

        // Latest record
        \App\Models\PriceRuleProduct::factory()->create([
            'product_id' => $product->id,
            'price_rule_id' => $activeRule->id,
            'final_selling_price' => 90.00,
            'updated_at' => now(),
        ]);

        $product->refresh();

        // Must return the latest record's final_selling_price
        $this->assertEquals(90.00, $product->selling_price);
    }

    public function test_selling_price_accessor_returns_correct_price_after_rule_update(): void
    {
        $product = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'face_value' => 100.00,
            'selling_price' => 100.00,
        ]);

        $priceRule = PriceRule::factory()->create([
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => Status::ACTIVE->value,
        ]);

        $updateData = [
            'name' => 'Updated Rule',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand_id',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $this->brand->id,
                ],
            ],
        ];

        // Apply initial rule: 100 - 10% = 90
        $this->service->updatePriceRuleWithConditions($priceRule, $updateData);
        $product->refresh();
        $this->assertEquals(90.00, $product->selling_price);

        // Update rule to 20% discount: 100 - 20% = 80
        $updateData['action_value'] = 20;
        $this->service->updatePriceRuleWithConditions($priceRule, $updateData);
        $product->refresh();
        $this->assertEquals(80.00, $product->selling_price);
    }

    public function test_selling_price_accessor_with_absolute_addition_rule(): void
    {
        $product = Product::factory()->create([
            'brand_id' => $this->brand->id,
            'face_value' => 100.00,
            'selling_price' => 100.00,
        ]);

        $data = [
            'name' => 'Absolute Add Rule',
            'description' => 'Add fixed amount to price',
            'match_type' => 'all',
            'action_operator' => ActionOperator::ADDITION->value,
            'action_mode' => ActionMode::ABSOLUTE->value,
            'action_value' => 25,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand_id',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $this->brand->id,
                ],
            ],
        ];

        $this->service->createPriceRuleWithConditions($data);

        $product->refresh();

        // 100 + 25 = 125
        $this->assertEquals(125.00, $product->selling_price);
    }
}
