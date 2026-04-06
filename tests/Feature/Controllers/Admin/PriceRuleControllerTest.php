<?php

namespace Tests\Feature\Controllers\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Brand;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\PriceRule;
use App\Models\DigitalProduct;
use App\Enums\PriceRule\Status;
use App\Enums\PriceRule\ActionMode;
use App\Enums\PriceRule\ActionOperator;
use App\Models\PriceRuleDigitalProduct;
use App\Enums\PriceRuleCondition\Operator;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PriceRuleControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;

    private Brand $brand;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'super_admin', 'is_approved' => true]);
        $this->brand = Brand::factory()->create();
        $this->supplier = Supplier::factory()->create();
    }

    public function test_index_returns_all_price_rules(): void
    {
        PriceRule::factory()->count(5)->create();

        $response = $this->actingAs($this->user)->getJson('/api/price-rules');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'data' => [
                    'current_page',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'description',
                            'match_type',
                            'action_operator',
                            'action_mode',
                            'action_value',
                            'status',
                            'created_at',
                        ],
                    ],
                    'last_page',
                    'total',
                ],

                'message',
            ])
            ->assertJson(['error' => false]);

        $this->assertCount(5, $response['data']['data']);
    }

    public function test_index_filters_by_match_type(): void
    {
        PriceRule::factory()->count(3)->create(['match_type' => 'all']);
        PriceRule::factory()->count(2)->create(['match_type' => 'any']);

        $response = $this->actingAs($this->user)->getJson('/api/price-rules?match_type=all');

        $response->assertStatus(200)
            ->assertJson(['error' => false]);

        $this->assertCount(3, $response['data']['data']);
    }

    public function test_index_filters_by_action_mode(): void
    {
        PriceRule::factory()->count(2)->create(['action_mode' => ActionMode::PERCENTAGE->value]);
        PriceRule::factory()->count(3)->create(['action_mode' => ActionMode::ABSOLUTE->value]);

        $response = $this->actingAs($this->user)->getJson('/api/price-rules?action_mode=percentage');

        $response->assertStatus(200)
            ->assertJson(['error' => false]);

        $this->assertCount(2, $response['data']['data']);
    }

    public function test_index_filters_by_status(): void
    {
        PriceRule::factory()->count(3)->create(['status' => Status::ACTIVE->value]);
        PriceRule::factory()->count(2)->create(['status' => Status::INACTIVE->value]);

        $response = $this->actingAs($this->user)->getJson('/api/price-rules?status=active');

        $response->assertStatus(200)
            ->assertJson(['error' => false]);

        $this->assertCount(3, $response['data']['data']);
    }

    public function test_index_with_pagination(): void
    {
        PriceRule::factory()->count(25)->create();

        $response = $this->actingAs($this->user)->getJson('/api/price-rules?per_page=10');

        $response->assertStatus(200);
        $this->assertEquals(10, $response['data']['per_page']);
    }

    public function test_store_creates_price_rule(): void
    {
        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'brand' => 'TestBrand',
        ]);

        $data = [
            'name' => 'New Price Rule',
            'description' => 'Apply discount to brand',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'supplier_name',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $this->supplier->name,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->postJson('/api/price-rules', $data);

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'data' => null,
                'message' => 'Price rule applied successfully.',
            ]);

        $this->assertDatabaseHas('price_rules', [
            'name' => 'New Price Rule',
            'action_mode' => ActionMode::PERCENTAGE->value,
        ]);

        $this->assertDatabaseHas('price_rule_conditions', [
            'field' => 'supplier_name',
        ]);
    }

    public function test_store_creates_price_rule_with_brand_name_contains(): void
    {
        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'brand' => 'Tech Corp Inc',
        ]);

        $data = [
            'name' => 'Brand Contains Rule',
            'description' => 'Apply discount to brands containing name',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 15,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand',
                    'operator' => Operator::CONTAINS->value,
                    'value' => 'Corp',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->postJson('/api/price-rules', $data);

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'data' => null,
                'message' => 'Price rule applied successfully.',
            ]);

        $this->assertDatabaseHas('price_rules', [
            'name' => 'Brand Contains Rule',
            'action_value' => 15,
        ]);

        $this->assertDatabaseHas('price_rule_conditions', [
            'field' => 'brand',
            'operator' => Operator::CONTAINS->value,
            'value' => 'Corp',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $data = [
            'name' => 'Incomplete Rule',
            // Missing other required fields
        ];

        $response = $this->actingAs($this->user)->postJson('/api/price-rules', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'match_type',
                'action_operator',
                'action_mode',
                'action_value',
                'status',
                'conditions',
            ]);
    }

    public function test_store_validates_action_value(): void
    {
        $data = [
            'name' => 'Rule',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => -5, // Negative value
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand_id',
                    'operator' => Operator::EQUAL->value,
                    'value' => '1',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->postJson('/api/price-rules', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['action_value']);
    }

    public function test_store_validates_conditions_array(): void
    {
        $data = [
            'name' => 'Rule',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => Status::ACTIVE->value,
            'conditions' => [], // Empty conditions array
        ];

        $response = $this->actingAs($this->user)->postJson('/api/price-rules', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['conditions']);
    }

    public function test_store_validates_operator_for_name_field(): void
    {
        $data = [
            'name' => 'Rule',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'name',
                    'operator' => Operator::GREATER_THAN->value,
                    'value' => 'test',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->postJson('/api/price-rules', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['conditions.0.operator']);
    }

    public function test_store_validates_operator_less_than_for_name_field(): void
    {
        $data = [
            'name' => 'Rule',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'name',
                    'operator' => Operator::LESS_THAN->value,
                    'value' => 'Hello',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->postJson('/api/price-rules', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['conditions.0.operator']);
    }

    public function test_store_validates_operator_for_selling_price_field(): void
    {
        $data = [
            'name' => 'Rule',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'selling_price',
                    'operator' => Operator::CONTAINS->value,
                    'value' => '100',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->postJson('/api/price-rules', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['conditions.0.operator']);
    }

    public function test_store_validates_operator_for_region_field(): void
    {
        $data = [
            'name' => 'Rule',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'region',
                    'operator' => Operator::CONTAINS->value,
                    'value' => 'US',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->postJson('/api/price-rules', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['conditions.0.operator']);
    }

    public function test_store_creates_price_rule_with_region(): void
    {
        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'region' => 'US',
        ]);

        $data = [
            'name' => 'Region Contains Rule',
            'description' => 'Apply discount to products in US region',
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 20,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'region',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'US',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->postJson('/api/price-rules', $data);

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'data' => null,
                'message' => 'Price rule applied successfully.',
            ]);

        $this->assertDatabaseHas('price_rules', [
            'name' => 'Region Contains Rule',
            'action_value' => 20,
        ]);

        $this->assertDatabaseHas('price_rule_conditions', [
            'field' => 'region',
            'operator' => Operator::EQUAL->value,
            'value' => 'US',
        ]);
    }

    public function test_store_creates_price_rule_with_multiple_conditions_including_contains(): void
    {
        $digitalProduct = DigitalProduct::factory()->create([
            'name' => 'Tech Gadget',
            'supplier_id' => $this->supplier->id,
            'brand' => 'Tech Corp',
            'region' => 'US',
        ]);

        $data = [
            'name' => 'Multiple Conditions Rule',
            'description' => 'Apply discount with multiple conditions',
            'match_type' => 'any',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 25,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'name',
                    'operator' => Operator::CONTAINS->value,
                    'value' => 'Tech',
                ],
                [
                    'field' => 'selling_price',
                    'operator' => Operator::GREATER_THAN->value,
                    'value' => '500',
                ],
                [
                    'field' => 'region',
                    'operator' => Operator::EQUAL->value,
                    'value' => 'US',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->postJson('/api/price-rules', $data);

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'data' => null,
                'message' => 'Price rule applied successfully.',
            ]);

        $this->assertDatabaseHas('price_rules', [
            'name' => 'Multiple Conditions Rule',
            'action_value' => 25,
            'match_type' => 'any',
        ]);

        $this->assertDatabaseHas('price_rule_conditions', [
            'field' => 'name',
            'operator' => Operator::CONTAINS->value,
            'value' => 'Tech',
        ]);

        $this->assertDatabaseHas('price_rule_conditions', [
            'field' => 'selling_price',
            'operator' => Operator::GREATER_THAN->value,
            'value' => '500',
        ]);

        $this->assertDatabaseHas('price_rule_conditions', [
            'field' => 'region',
            'operator' => Operator::EQUAL->value,
            'value' => 'US',
        ]);
    }

    public function test_show_returns_price_rule_with_conditions(): void
    {
        $priceRule = PriceRule::factory()->create();

        $priceRule->conditions()->create([
            'field' => 'brand_id',
            'operator' => Operator::EQUAL->value,
            'value' => '5',
        ]);

        $priceRule->conditions()->create([
            'field' => 'selling_price',
            'operator' => Operator::GREATER_THAN->value,
            'value' => '100',
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/price-rules/{$priceRule->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'match_type',
                    'action_operator',
                    'action_mode',
                    'action_value',
                    'status',
                    'conditions' => [
                        '*' => [
                            'id',
                            'field',
                            'operator',
                            'value',
                        ],
                    ],
                    'created_at',
                ],
                'message',
            ])
            ->assertJson([
                'error' => false,
                'data' => [
                    'id' => $priceRule->id,
                    'name' => $priceRule->name,
                ],
            ]);

        $this->assertCount(2, $response['data']['conditions']);
    }

    public function test_show_returns_404_for_nonexistent_rule(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/price-rules/999');

        $response->assertStatus(404);
    }

    public function test_update_updates_price_rule(): void
    {
        $priceRule = PriceRule::factory()->create([
            'name' => 'Original Rule',
            'action_value' => 5,
        ]);

        $data = [
            'name' => 'Updated Rule',
            'description' => 'Updated description',
            'match_type' => 'all',
            'action_operator' => ActionOperator::ADDITION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 15,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $this->brand->name,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->postJson("/api/price-rules/{$priceRule->id}", $data);

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Price rule updated successfully.',
            ]);

        $this->assertDatabaseHas('price_rules', [
            'id' => $priceRule->id,
            'name' => 'Updated Rule',
            'action_value' => 15,
        ]);
    }

    public function test_update_replaces_conditions(): void
    {
        $priceRule = PriceRule::factory()->create();
        $priceRule->conditions()->create([
            'field' => 'old_field',
            'operator' => Operator::EQUAL->value,
            'value' => 'old_value',
        ]);

        $data = [
            'name' => $priceRule->name,
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                [
                    'field' => 'brand',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $this->brand->name,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->postJson("/api/price-rules/{$priceRule->id}", $data);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('price_rule_conditions', [
            'price_rule_id' => $priceRule->id,
            'field' => 'old_field',
        ]);

        $this->assertDatabaseHas('price_rule_conditions', [
            'price_rule_id' => $priceRule->id,
            'field' => 'brand',
        ]);
    }

    public function test_update_does_not_stack_price_rule_digital_product_records(): void
    {
        $digitalProduct = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'brand' => 'TestBrand',
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

        $data = [
            'name' => $priceRule->name,
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 20,
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
        $this->actingAs($this->user)->postJson("/api/price-rules/{$priceRule->id}", $data);
        $this->actingAs($this->user)->postJson("/api/price-rules/{$priceRule->id}", $data)->assertStatus(200);

        // Exactly 1 record per digital product — no stacking
        $count = PriceRuleDigitalProduct::where('price_rule_id', $priceRule->id)
            ->where('digital_product_id', $digitalProduct->id)
            ->count();

        $this->assertEquals(1, $count);

        // Price reflects the latest update: 100 - 20% = 80
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
            'brand' => 'BrandA',
            'face_value' => 100.00,
            'selling_price' => 100.00,
        ]);
        $dp2 = DigitalProduct::factory()->create([
            'supplier_id' => $this->supplier->id,
            'brand' => 'BrandB',
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

        // First update — targets BrandA
        $this->actingAs($this->user)->postJson("/api/price-rules/{$priceRule->id}", [
            'name' => $priceRule->name,
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                ['field' => 'brand', 'operator' => Operator::EQUAL->value, 'value' => 'BrandA'],
            ],
        ])->assertStatus(200);

        $this->assertDatabaseHas('price_rule_digital_product', ['digital_product_id' => $dp1->id, 'price_rule_id' => $priceRule->id]);
        $this->assertDatabaseMissing('price_rule_digital_product', ['digital_product_id' => $dp2->id, 'price_rule_id' => $priceRule->id]);

        // Second update — conditions now target BrandB instead
        $this->actingAs($this->user)->postJson("/api/price-rules/{$priceRule->id}", [
            'name' => $priceRule->name,
            'match_type' => 'all',
            'action_operator' => ActionOperator::SUBTRACTION->value,
            'action_mode' => ActionMode::PERCENTAGE->value,
            'action_value' => 10,
            'status' => Status::ACTIVE->value,
            'conditions' => [
                ['field' => 'brand', 'operator' => Operator::EQUAL->value, 'value' => 'BrandB'],
            ],
        ])->assertStatus(200);

        // dp1's old record must be gone; dp2's fresh record must exist
        $this->assertDatabaseMissing('price_rule_digital_product', ['digital_product_id' => $dp1->id, 'price_rule_id' => $priceRule->id]);
        $this->assertDatabaseHas('price_rule_digital_product', ['digital_product_id' => $dp2->id, 'price_rule_id' => $priceRule->id]);
    }

    public function test_update_validates_operator_for_name_field(): void
    {
        $priceRule = PriceRule::factory()->create();

        $data = [
            'name' => $priceRule->name,
            'conditions' => [
                [
                    'field' => 'name',
                    'operator' => Operator::GREATER_THAN->value,
                    'value' => 'test',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->postJson("/api/price-rules/{$priceRule->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['conditions.0.operator']);
    }

    public function test_update_validates_operator_for_selling_price_field(): void
    {
        $priceRule = PriceRule::factory()->create();

        $data = [
            'name' => $priceRule->name,
            'conditions' => [
                [
                    'field' => 'selling_price',
                    'operator' => Operator::CONTAINS->value,
                    'value' => '100',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->postJson("/api/price-rules/{$priceRule->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['conditions.0.operator']);
    }

    public function test_update_validates_operator_for_region_field(): void
    {
        $priceRule = PriceRule::factory()->create();

        $data = [
            'name' => $priceRule->name,
            'conditions' => [
                [
                    'field' => 'region',
                    'operator' => Operator::CONTAINS->value,
                    'value' => 'US',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->postJson("/api/price-rules/{$priceRule->id}", $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['conditions.0.operator']);
    }

    public function test_destroy_deletes_price_rule(): void
    {
        $priceRule = PriceRule::factory()->create();
        $priceRule->conditions()->create([
            'field' => 'brand_id',
            'operator' => Operator::EQUAL->value,
            'value' => '5',
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/price-rules/{$priceRule->id}");

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Price rule deleted successfully.',
            ]);

        $this->assertDatabaseMissing('price_rules', ['id' => $priceRule->id]);
        $this->assertDatabaseMissing('price_rule_conditions', ['price_rule_id' => $priceRule->id]);
    }

    public function test_destroy_returns_404_for_nonexistent_rule(): void
    {
        $response = $this->actingAs($this->user)->deleteJson('/api/price-rules/999');

        $response->assertStatus(404);
    }

    public function test_post_view_digital_products_returns_digital_products_for_price_rule(): void
    {
        $priceRule = PriceRule::factory()->create();
        $dp1 = DigitalProduct::factory()->create(['supplier_id' => $this->supplier->id]);
        $dp2 = DigitalProduct::factory()->create(['supplier_id' => $this->supplier->id]);

        PriceRuleDigitalProduct::factory()->create([
            'price_rule_id' => $priceRule->id,
            'digital_product_id' => $dp1->id,
            'final_selling_price' => 90.00,
        ]);
        PriceRuleDigitalProduct::factory()->create([
            'price_rule_id' => $priceRule->id,
            'digital_product_id' => $dp2->id,
            'final_selling_price' => 135.00,
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/price-rules/{$priceRule->id}/digital-products");

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Price rule digital products retrieved successfully.',
            ]);

        $this->assertCount(2, $response['data']['data']);
    }

    public function test_post_view_digital_products_returns_correct_structure(): void
    {
        $priceRule = PriceRule::factory()->create();
        $digitalProduct = DigitalProduct::factory()->create(['supplier_id' => $this->supplier->id]);

        PriceRuleDigitalProduct::factory()->create([
            'price_rule_id' => $priceRule->id,
            'digital_product_id' => $digitalProduct->id,
            'original_selling_price' => 100.00,
            'base_value' => 100.00,
            'action_mode' => 'percentage',
            'action_operator' => 'subtract',
            'action_value' => 10.00,
            'calculated_price' => 90.00,
            'final_selling_price' => 90.00,
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/price-rules/{$priceRule->id}/digital-products");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
                'data' => [
                    'current_page',
                    'data' => [
                        '*' => [
                            'id',
                            'digital_product_id',
                            'price_rule_id',
                            'original_selling_price',
                            'base_value',
                            'action_mode',
                            'action_operator',
                            'action_value',
                            'calculated_price',
                            'final_selling_price',
                            'applied_at',
                            'digital_product',
                        ],
                    ],
                    'last_page',
                    'total',
                ],
                'message',
            ]);

        $item = $response['data']['data'][0];
        $this->assertEquals($priceRule->id, $item['price_rule_id']);
        $this->assertEquals($digitalProduct->id, $item['digital_product_id']);
        $this->assertEquals('90.00', $item['final_selling_price']);
    }

    public function test_post_view_digital_products_returns_empty_for_rule_with_no_applications(): void
    {
        $priceRule = PriceRule::factory()->create();

        $response = $this->actingAs($this->user)->getJson("/api/price-rules/{$priceRule->id}/digital-products");

        $response->assertStatus(200)
            ->assertJson([
                'error' => false,
                'message' => 'Price rule digital products retrieved successfully.',
            ]);

        $this->assertCount(0, $response['data']['data']);
    }

    public function test_post_view_digital_products_does_not_return_other_rules_digital_products(): void
    {
        $priceRule1 = PriceRule::factory()->create();
        $priceRule2 = PriceRule::factory()->create();
        $digitalProduct = DigitalProduct::factory()->create(['supplier_id' => $this->supplier->id]);

        PriceRuleDigitalProduct::factory()->create([
            'price_rule_id' => $priceRule1->id,
            'digital_product_id' => $digitalProduct->id,
        ]);
        PriceRuleDigitalProduct::factory()->create([
            'price_rule_id' => $priceRule2->id,
            'digital_product_id' => $digitalProduct->id,
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/price-rules/{$priceRule1->id}/digital-products");

        $response->assertStatus(200);
        $this->assertCount(1, $response['data']['data']);
        $this->assertEquals($priceRule1->id, $response['data']['data'][0]['price_rule_id']);
    }

    public function test_post_view_digital_products_returns_404_for_nonexistent_rule(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/price-rules/999/digital-products');

        $response->assertStatus(404);
    }

    public function test_post_view_digital_products_returns_paginated_results(): void
    {
        $priceRule = PriceRule::factory()->create();

        PriceRuleDigitalProduct::factory()->count(20)->create([
            'price_rule_id' => $priceRule->id,
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/price-rules/{$priceRule->id}/digital-products");

        $response->assertStatus(200);
        $this->assertEquals(15, $response['data']['per_page']);
        $this->assertEquals(20, $response['data']['total']);
        $this->assertCount(15, $response['data']['data']);
    }
}
