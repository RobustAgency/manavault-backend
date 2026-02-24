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
}
