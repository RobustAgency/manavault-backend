<?php

namespace Tests\Feature\Services;

use Tests\TestCase;
use App\Models\Brand;
use App\Models\Supplier;
use App\Models\PriceRule;
use App\Models\DigitalProduct;
use App\Enums\PriceRule\Status;
use App\Enums\PriceRule\ActionMode;
use App\Services\PricingRuleService;
use App\Enums\PriceRule\ActionOperator;
use App\Models\PriceRuleDigitalProduct;
use App\Enums\PriceRuleCondition\Operator;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PricingRuleServiceTest extends TestCase
{
    use RefreshDatabase;

    private PricingRuleService $service;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PricingRuleService::class);
        $this->supplier = Supplier::factory()->create();
    }

    public function test_create_price_rule_with_conditions(): void
    {
        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'cost_price' => 98.25,
            'face_value' => 100.00,
            'selling_price' => 100.00,
            'brand' => 'TestBrand',
        ]);
        // cost_price is 98.25; after 1% subtraction final = 99.00 > cost_price, so rule applies normally.

        $data = [
            'name' => 'Test Price Rule',
            'description' => 'Apply discount to brand',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 1,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'TestBrand',
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
            'field' => 'brand',
            'operator' => Operator::EQUAL->value,
        ]);

        // Price should be reduced by 10%: 100 - (100 * 0.10) = 90
        $this->assertDatabaseHas('price_rule_digital_product', [
            'digital_product_id' => $digitalProduct->id,
            'final_selling_price' => 99.00,
        ]);

        // Digital product selling_price should be updated
        $digitalProduct->refresh();
        $this->assertEquals(99.00, (float) $digitalProduct->selling_price);
    }

    public function test_create_price_rule_with_percentage_addition(): void
    {
        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'cost_price' => 98.25,
            'face_value' => 100.00,
            'selling_price' => 100.00,
            'brand' => 'TestBrand',
        ]);

        $data = [
            'name' => 'Markup Rule',
            'description' => 'Add markup to digital products',
            'match_type' => 'all',
            'action_operator' => ActionOperator::ADDITION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 2,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'TestBrand',
                ],
            ],
        ];

        $this->service->createPriceRuleWithConditions($data);

        // Price should be increased by 2%: 100 + (100 * 0.02) = 102
        $this->assertDatabaseHas('price_rule_digital_product', [
            'digital_product_id' => $digitalProduct->id,
            'final_selling_price' => 102.00,
        ]);

        $digitalProduct->refresh();
        $this->assertEquals(102.00, (float) $digitalProduct->selling_price);
    }

    public function test_create_price_rule_with_absolute_value(): void
    {
        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'cost_price' => 98.25,
            'face_value' => 100.00,
            'selling_price' => 100.00,
            'brand' => 'TestBrand',
        ]);

        $data = [
            'name' => 'Fixed Discount Rule',
            'description' => 'Apply fixed discount',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::ABSOLUTE->value,
            'action_value' => 1.5,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'TestBrand',
                ],
            ],
        ];

        $this->service->createPriceRuleWithConditions($data);

        // Price should be reduced by fixed amount: 100 - 1.5 = 98.50 (above cost_price 98.25, so applies normally)
        $this->assertDatabaseHas('price_rule_digital_product', [
            'digital_product_id' => $digitalProduct->id,
            'final_selling_price' => 98.50,
        ]);

        $digitalProduct->refresh();
        $this->assertEquals(98.50, (float) $digitalProduct->selling_price);
    }

    public function test_create_price_rule_with_multiple_conditions_all(): void
    {
        $dp1 = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'brand' => 'BrandA',
            'cost_price' => 48.25,
            'selling_price' => 50.00,
            'face_value' => 50.00,
        ]);
        $dp2 = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'brand' => 'BrandA',
            'cost_price' => 148.25,
            'selling_price' => 150.00,
            'face_value' => 150.00,
        ]);
        $dp3 = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'brand' => 'BrandB',
            'cost_price' => 148.25,
            'selling_price' => 150.00,
            'face_value' => 150.00,
        ]);

        $data = [
            'name' => 'Premium Brand Discount',
            'description' => 'Discount for premium digital products of specific brand',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 0.1,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'BrandA',
                ],
                [
                    'field' => 'selling_price',
                    'operator' => Operator::GREATER_THAN_OR_EQUAL->value,
                    'value' => '100',
                ],
            ],
        ];

        $this->service->createPriceRuleWithConditions($data);

        // Only dp2 should be discounted (brand matches AND price >= 100)
        $this->assertDatabaseMissing('price_rule_digital_product', ['digital_product_id' => $dp1->id]);
        $this->assertDatabaseHas('price_rule_digital_product', [
            'digital_product_id' => $dp2->id,
            'final_selling_price' => 149.85, // 150 - (150 * 0.001) = 149.85; clamped to cost_price (148.25) minimum
        ]);
        $this->assertDatabaseMissing('price_rule_digital_product', ['digital_product_id' => $dp3->id]);
    }

    public function test_create_price_rule_with_multiple_conditions_any(): void
    {
        $dp1 = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'brand' => 'BrandA',
            'cost_price' => 40.00,
            'selling_price' => 50.00,
            'face_value' => 50.00,
        ]);
        $dp2 = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'brand' => 'BrandB',
            'cost_price' => 40.00,
            'selling_price' => 50.00,
            'face_value' => 50.00,
        ]);
        $dp3 = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'brand' => 'BrandB',
            'cost_price' => 130.00,
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
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'BrandA',
                ],
                [
                    'field' => 'selling_price',
                    'operator' => Operator::GREATER_THAN_OR_EQUAL->value,
                    'value' => '100',
                ],
            ],
        ];

        $this->service->createPriceRuleWithConditions($data);

        // dp1 matches brand condition, dp3 matches price condition
        $this->assertDatabaseHas('price_rule_digital_product', [
            'digital_product_id' => $dp1->id,
            'final_selling_price' => 47.50, // 50 - (50 * 0.05) = 47.5
        ]);
        $this->assertDatabaseMissing('price_rule_digital_product', ['digital_product_id' => $dp2->id]);
        $this->assertDatabaseHas('price_rule_digital_product', [
            'digital_product_id' => $dp3->id,
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

        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'face_value' => 100.00,
            'selling_price' => 100.00,
            'cost_price' => 80.00,
            'brand' => 'TestBrand',
        ]);

        $updateData = [
            'name' => 'Updated Rule',
            'action_value' => 15,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'conditions' => [
                [
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'TestBrand',
                ],
            ],
        ];

        $this->service->updatePriceRuleWithConditions($priceRule, $updateData);

        $this->assertDatabaseHas('price_rules', [
            'id' => $priceRule->id,
            'name' => 'Updated Rule',
            'action_value' => 15,
        ]);

        // Price should be reduced by 15%: 100 - (100 * 0.15) = 85 (above cost_price 80.00)
        $this->assertDatabaseHas('price_rule_digital_product', [
            'digital_product_id' => $digitalProduct->id,
            'price_rule_id' => $priceRule->id,
            'final_selling_price' => 85.00,
        ]);

        $digitalProduct->refresh();
        $this->assertEquals(85.00, (float) $digitalProduct->selling_price);
    }

    public function test_update_does_not_stack_price_rule_digital_product_records(): void
    {
        $priceRule = PriceRule::factory()->create([
            'name' => 'Rule',
            'match_type' => 'all',
            'action_value' => 10,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'status' => Status::ACTIVE->value,
        ]);

        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'face_value' => 100.00,
            'selling_price' => 100.00,
            'cost_price' => 70.00,
            'brand' => 'TestBrand',
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
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'TestBrand',
                ],
            ],
        ];

        // Call update twice to simulate multiple saves
        $this->service->updatePriceRuleWithConditions($priceRule, $updateData);
        $this->service->updatePriceRuleWithConditions($priceRule, $updateData);

        // There must be exactly 1 record per digital product, not stacked duplicates
        $count = PriceRuleDigitalProduct::where('price_rule_id', $priceRule->id)
            ->where('digital_product_id', $digitalProduct->id)
            ->count();

        $this->assertEquals(1, $count);

        // And the price should reflect the latest update: 100 - 20% = 80 (above cost_price 70.00)
        $this->assertDatabaseHas('price_rule_digital_product', [
            'digital_product_id' => $digitalProduct->id,
            'price_rule_id' => $priceRule->id,
            'final_selling_price' => 80.00,
        ]);
    }

    public function test_update_clears_stale_digital_products_when_conditions_change(): void
    {
        $dp1 = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'face_value' => 100.00,
            'selling_price' => 100.00,
            'cost_price' => 80.00,
            'brand' => 'BrandA',
        ]);
        $dp2 = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'face_value' => 100.00,
            'selling_price' => 100.00,
            'cost_price' => 80.00,
            'brand' => 'BrandB',
        ]);

        $priceRule = PriceRule::factory()->create([
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => Status::ACTIVE->value,
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
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'BrandA',
                ],
            ],
        ];

        $this->service->updatePriceRuleWithConditions($priceRule, $initialData);

        // dp1 should be in price_rule_digital_product, dp2 should not
        $this->assertDatabaseHas('price_rule_digital_product', ['digital_product_id' => $dp1->id, 'price_rule_id' => $priceRule->id]);
        $this->assertDatabaseMissing('price_rule_digital_product', ['digital_product_id' => $dp2->id, 'price_rule_id' => $priceRule->id]);

        // Now update to target BrandB instead
        $updatedData = [
            'name' => 'Rule',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'BrandB',
                ],
            ],
        ];

        $this->service->updatePriceRuleWithConditions($priceRule, $updatedData);

        // dp1's old record must be gone, dp2's new record must exist
        $this->assertDatabaseMissing('price_rule_digital_product', ['digital_product_id' => $dp1->id, 'price_rule_id' => $priceRule->id]);
        $this->assertDatabaseHas('price_rule_digital_product', ['digital_product_id' => $dp2->id, 'price_rule_id' => $priceRule->id]);
    }

    public function test_update_price_rule_replaces_conditions(): void
    {
        $priceRule = PriceRule::factory()->create();

        $dp1 = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'face_value' => 100.00,
            'selling_price' => 100.00,
            'cost_price' => 80.00,
            'brand' => 'BrandA',
        ]);
        $dp2 = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'face_value' => 100.00,
            'selling_price' => 100.00,
            'cost_price' => 80.00,
            'brand' => 'BrandB',
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
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'BrandA',
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
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'BrandB',
                ],
            ],
        ];

        $this->service->updatePriceRuleWithConditions($priceRule, $updateData);

        // Old condition should be deleted, new one should exist
        $this->assertDatabaseMissing('price_rule_conditions', [
            'price_rule_id' => $priceRule->id,
            'field' => 'brand',
            'value' => 'BrandA',
        ]);

        $this->assertDatabaseHas('price_rule_conditions', [
            'price_rule_id' => $priceRule->id,
            'field' => 'brand',
            'value' => 'BrandB',
        ]);
    }

    public function test_selling_price_cannot_be_negative(): void
    {
        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'face_value' => 50.00,
            'cost_price' => 0.00,
            'selling_price' => 50.00,
            'brand' => 'TestBrand',
        ]);

        $data = [
            'name' => 'Extreme Discount',
            'description' => 'Discount larger than price',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::ABSOLUTE->value,
            'action_value' => 100, // More than the digital product price
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'TestBrand',
                ],
            ],
        ];

        $this->service->createPriceRuleWithConditions($data);

        // Price should be 0 minimum, not negative
        $this->assertDatabaseHas('price_rule_digital_product', [
            'digital_product_id' => $digitalProduct->id,
            'final_selling_price' => 0.00,
        ]);

        $digitalProduct->refresh();
        $this->assertEquals(0.00, (float) $digitalProduct->selling_price);
    }

    public function test_create_rule_with_no_matching_digital_products(): void
    {
        DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'face_value' => 100.00,
            'cost_price' => 80.00,
            'selling_price' => 100.00,
            'brand' => 'OtherBrand',
        ]);

        $data = [
            'name' => 'No Match Rule',
            'description' => 'Rule that matches no digital products',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'NonExistentBrand',
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
    // Digital product selling_price updated after rule application
    // -------------------------------------------------------------------------

    public function test_digital_product_selling_price_updated_after_percentage_subtraction(): void
    {
        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'face_value' => 100.00,
            'cost_price' => 80.00,
            'selling_price' => 100.00,
            'brand' => 'TestBrand',
        ]);

        $data = [
            'name' => 'Selling Price Update Test',
            'description' => 'Test selling price is persisted',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'TestBrand',
                ],
            ],
        ];

        $this->service->createPriceRuleWithConditions($data);

        $digitalProduct->refresh();

        // selling_price must be 90.00 (100 - 10%)
        $this->assertEquals(90.00, (float) $digitalProduct->selling_price);
    }

    public function test_digital_product_selling_price_updated_after_rule_update(): void
    {
        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'face_value' => 100.00,
            'cost_price' => 70.00,
            'selling_price' => 100.00,
            'brand' => 'TestBrand',
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
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'TestBrand',
                ],
            ],
        ];

        // Apply initial rule: 100 - 10% = 90
        $this->service->updatePriceRuleWithConditions($priceRule, $updateData);
        $digitalProduct->refresh();
        $this->assertEquals(90.00, (float) $digitalProduct->selling_price);

        // Update rule to 20% discount: 100 - 20% = 80
        // Note: face_value remains 100, so recalculation uses face_value as base
        $updateData['action_value'] = 20;
        $this->service->updatePriceRuleWithConditions($priceRule, $updateData);
        $digitalProduct->refresh();
        $this->assertEquals(80.00, (float) $digitalProduct->selling_price);
    }

    public function test_digital_product_selling_price_updated_with_absolute_addition(): void
    {
        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'face_value' => 100.00,
            'cost_price' => 80.00,
            'selling_price' => 100.00,
            'brand' => 'TestBrand',
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
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'TestBrand',
                ],
            ],
        ];

        $this->service->createPriceRuleWithConditions($data);

        $digitalProduct->refresh();

        // 100 + 25 = 125
        $this->assertEquals(125.00, (float) $digitalProduct->selling_price);
    }

    public function test_face_value_is_used_as_base_for_calculations_not_cost_price(): void
    {
        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'face_value' => 100.00,
            'cost_price' => 60.00,
            'selling_price' => 80.00,
            'brand' => 'TestBrand',
        ]);

        $data = [
            'name' => 'Face Value Base Test',
            'description' => 'Verify face_value is the calculation base',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'TestBrand',
                ],
            ],
        ];

        $this->service->createPriceRuleWithConditions($data);

        // Base is face_value (100), NOT cost_price (60): 100 - 10% = 90
        $this->assertDatabaseHas('price_rule_digital_product', [
            'digital_product_id' => $digitalProduct->id,
            'base_value' => 100.00,
            'final_selling_price' => 90.00,
        ]);

        $digitalProduct->refresh();
        $this->assertEquals(90.00, (float) $digitalProduct->selling_price);
    }

    public function test_conditions_filter_by_supplier_id(): void
    {
        $supplier2 = Supplier::factory()->create();

        $dp1 = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'face_value' => 100.00,
            'cost_price' => 80.00,
            'selling_price' => 100.00,
        ]);
        $dp2 = DigitalProduct::factory()->create([
            'supplier_id' => $supplier2->id,
            'face_value' => 100.00,
            'cost_price' => 80.00,
            'selling_price' => 100.00,
        ]);

        $data = [
            'name' => 'Supplier Filter Rule',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'supplier_id',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $this->supplier->id,
                ],
            ],
        ];

        $this->service->createPriceRuleWithConditions($data);

        $this->assertDatabaseHas('price_rule_digital_product', ['digital_product_id' => $dp1->id]);
        $this->assertDatabaseMissing('price_rule_digital_product', ['digital_product_id' => $dp2->id]);
    }

    public function test_conditions_filter_by_currency(): void
    {
        $dp1 = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'face_value' => 100.00,
            'cost_price' => 80.00,
            'selling_price' => 100.00,
            'currency' => 'usd',
        ]);
        $dp2 = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'face_value' => 100.00,
            'cost_price' => 80.00,
            'selling_price' => 100.00,
            'currency' => 'eur',
        ]);

        $data = [
            'name' => 'Currency Filter Rule',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 5,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'currency',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'usd',
                ],
            ],
        ];

        $this->service->createPriceRuleWithConditions($data);

        $this->assertDatabaseHas('price_rule_digital_product', ['digital_product_id' => $dp1->id]);
        $this->assertDatabaseMissing('price_rule_digital_product', ['digital_product_id' => $dp2->id]);
    }

    public function test_conditions_filter_by_region(): void
    {
        $dp1 = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'face_value' => 100.00,
            'cost_price' => 80.00,
            'selling_price' => 100.00,
            'region' => 'US',
        ]);
        $dp2 = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'face_value' => 100.00,
            'cost_price' => 80.00,
            'selling_price' => 100.00,
            'region' => 'EU',
        ]);

        $data = [
            'name' => 'Region Filter Rule',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 5,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'region',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'US',
                ],
            ],
        ];

        $this->service->createPriceRuleWithConditions($data);

        $this->assertDatabaseHas('price_rule_digital_product', ['digital_product_id' => $dp1->id]);
        $this->assertDatabaseMissing('price_rule_digital_product', ['digital_product_id' => $dp2->id]);
    }

    public function test_conditions_filter_by_face_value(): void
    {
        $dp1 = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'face_value' => 50.00,
            'cost_price' => 40.00,
            'selling_price' => 50.00,
            'brand' => 'TestBrand',
        ]);
        $dp2 = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'face_value' => 150.00,
            'cost_price' => 120.00,
            'selling_price' => 150.00,
            'brand' => 'TestBrand',
        ]);

        $data = [
            'name' => 'Face Value Filter Rule',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'face_value',
                    'operator' => Operator::GREATER_THAN_OR_EQUAL->value,
                    'value' => '100',
                ],
            ],
        ];

        $this->service->createPriceRuleWithConditions($data);

        $this->assertDatabaseMissing('price_rule_digital_product', ['digital_product_id' => $dp1->id]);
        $this->assertDatabaseHas('price_rule_digital_product', [
            'digital_product_id' => $dp2->id,
            'final_selling_price' => 135.00, // 150 - (150 * 0.10) = 135
        ]);
    }

    public function test_conditions_filter_by_cost_price(): void
    {
        $dp1 = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'face_value' => 50.00,
            'cost_price' => 30.00,
            'selling_price' => 50.00,
        ]);
        $dp2 = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'face_value' => 150.00,
            'cost_price' => 120.00,
            'selling_price' => 150.00,
        ]);

        $data = [
            'name' => 'Cost Price Filter Rule',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'cost_price',
                    'operator' => Operator::GREATER_THAN_OR_EQUAL->value,
                    'value' => '100',
                ],
            ],
        ];

        $this->service->createPriceRuleWithConditions($data);

        $this->assertDatabaseMissing('price_rule_digital_product', ['digital_product_id' => $dp1->id]);
        $this->assertDatabaseHas('price_rule_digital_product', ['digital_product_id' => $dp2->id]);
    }

    // -------------------------------------------------------------------------
    // Selling price cannot fall below cost price
    // -------------------------------------------------------------------------

    public function test_digital_product_is_skipped_when_discount_would_go_below_cost_price(): void
    {
        // face_value=100, cost_price=95, discount=10% => calculated=90 < cost_price=95
        // Expected: product is skipped — no record created, selling_price unchanged
        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'face_value' => 100.00,
            'cost_price' => 95.00,
            'selling_price' => 100.00,
            'brand' => 'TestBrand',
        ]);

        $data = [
            'name' => 'Large Discount Rule',
            'description' => 'Discount that would push price below cost',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'TestBrand',
                ],
            ],
        ];

        $this->service->createPriceRuleWithConditions($data);

        // Product must be skipped — no pivot record, selling_price unchanged
        $this->assertDatabaseMissing('price_rule_digital_product', [
            'digital_product_id' => $digitalProduct->id,
        ]);

        $digitalProduct->refresh();
        $this->assertEquals(100.00, (float) $digitalProduct->selling_price);
    }

    public function test_digital_product_is_skipped_when_absolute_discount_goes_below_cost_price(): void
    {
        // face_value=50, cost_price=48, absolute discount=10 => calculated=40 < cost_price=48
        // Expected: product is skipped — no record created, selling_price unchanged
        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'face_value' => 50.00,
            'cost_price' => 48.00,
            'selling_price' => 50.00,
            'brand' => 'TestBrand',
        ]);

        $data = [
            'name' => 'Absolute Discount Below Cost',
            'description' => 'Absolute discount that would push price below cost',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::ABSOLUTE->value,
            'action_value' => 10,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'TestBrand',
                ],
            ],
        ];

        $this->service->createPriceRuleWithConditions($data);

        // Product must be skipped — no pivot record, selling_price unchanged
        $this->assertDatabaseMissing('price_rule_digital_product', [
            'digital_product_id' => $digitalProduct->id,
        ]);

        $digitalProduct->refresh();
        $this->assertEquals(50.00, (float) $digitalProduct->selling_price);
    }

    public function test_digital_product_is_applied_when_discount_stays_above_cost_price(): void
    {
        // face_value=100, cost_price=85, discount=10% => calculated=90 > cost_price=85
        // Expected: rule applies normally — record saved, selling_price updated
        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'face_value' => 100.00,
            'cost_price' => 85.00,
            'selling_price' => 100.00,
            'brand' => 'TestBrand',
        ]);

        $data = [
            'name' => 'Safe Discount Rule',
            'description' => 'Discount that stays above cost price',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'TestBrand',
                ],
            ],
        ];

        $this->service->createPriceRuleWithConditions($data);

        // Price is 90 which is above cost_price 85 — applies normally
        $this->assertDatabaseHas('price_rule_digital_product', [
            'digital_product_id' => $digitalProduct->id,
            'final_selling_price' => 90.00,
        ]);

        $digitalProduct->refresh();
        $this->assertEquals(90.00, (float) $digitalProduct->selling_price);
    }

    public function test_mixed_products_some_skipped_some_applied_based_on_cost_price(): void
    {
        // dp1: face_value=100, cost_price=50  → 10% off = 90 > 50  → applied normally
        // dp2: face_value=100, cost_price=95  → 10% off = 90 < 95  → skipped entirely
        $dp1 = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'face_value' => 100.00,
            'cost_price' => 50.00,
            'selling_price' => 100.00,
            'brand' => 'MixedBrand',
        ]);
        $dp2 = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'face_value' => 100.00,
            'cost_price' => 95.00,
            'selling_price' => 100.00,
            'brand' => 'MixedBrand',
        ]);

        $data = [
            'name' => 'Mixed Margin Rule',
            'description' => 'Rule applied to products with different margins',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'MixedBrand',
                ],
            ],
        ];

        $this->service->createPriceRuleWithConditions($data);

        // dp1: 90 > cost_price 50 — applied
        $this->assertDatabaseHas('price_rule_digital_product', [
            'digital_product_id' => $dp1->id,
            'final_selling_price' => 90.00,
        ]);

        // dp2: 90 < cost_price 95 — skipped, no record, selling_price unchanged
        $this->assertDatabaseMissing('price_rule_digital_product', [
            'digital_product_id' => $dp2->id,
        ]);

        $dp1->refresh();
        $dp2->refresh();
        $this->assertEquals(90.00, (float) $dp1->selling_price);
        $this->assertEquals(100.00, (float) $dp2->selling_price); // unchanged
    }

    public function test_update_rule_skips_product_when_new_discount_goes_below_cost_price(): void
    {
        // Initially a safe discount, then updated to a discount that exceeds margin
        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'face_value' => 100.00,
            'cost_price' => 85.00,
            'selling_price' => 100.00,
            'brand' => 'TestBrand',
        ]);

        $priceRule = PriceRule::factory()->create([
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 5,
            'status' => Status::ACTIVE->value,
        ]);

        // First update: 5% off = 95 > cost_price 85 → applied normally
        $updateData = [
            'name' => 'Growing Discount Rule',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 5,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'TestBrand',
                ],
            ],
        ];

        $this->service->updatePriceRuleWithConditions($priceRule, $updateData);
        $digitalProduct->refresh();
        $this->assertEquals(95.00, (float) $digitalProduct->selling_price);

        // Second update: 20% off = 80 < cost_price 85 → product skipped entirely
        $updateData['action_value'] = 20;
        $this->service->updatePriceRuleWithConditions($priceRule, $updateData);

        // No pivot record for the new discount
        $this->assertDatabaseMissing('price_rule_digital_product', [
            'digital_product_id' => $digitalProduct->id,
            'price_rule_id' => $priceRule->id,
        ]);

        // The previous pivot record was deleted by deleteByPriceRuleId, and the new one was skipped.
        // getSellingPriceAttribute() falls back to the raw selling_price column, which was never
        // directly written — it remains at its original value (100.00).
        $digitalProduct->refresh();
        $this->assertEquals(100.00, (float) $digitalProduct->selling_price);
    }
}
