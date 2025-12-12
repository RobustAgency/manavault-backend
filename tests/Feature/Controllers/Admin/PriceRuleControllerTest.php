<?php

namespace Tests\Feature\Controllers\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Brand;
use App\Models\Product;
use App\Models\PriceRule;
use App\Enums\PriceRule\Status;
use App\Enums\PriceRule\ActionMode;
use App\Enums\PriceRule\ActionOperator;
use App\Enums\PriceRuleCondition\Operator;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PriceRuleControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'admin']);
        $this->brand = Brand::factory()->create();
    }

    public function test_index_returns_all_price_rules(): void
    {
        PriceRule::factory()->count(5)->create();

        $response = $this->actingAs($this->user)->getJson('/api/admin/price-rules');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'error',
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
                'message',
            ])
            ->assertJson(['error' => false]);

        $this->assertCount(5, $response['data']);
    }

    public function test_index_filters_by_name(): void
    {
        PriceRule::factory()->create(['name' => 'Premium Discount']);
        PriceRule::factory()->create(['name' => 'Budget Offer']);
        PriceRule::factory()->create(['name' => 'Premium Sale']);

        $response = $this->actingAs($this->user)->getJson('/api/admin/price-rules?name=Premium');

        $response->assertStatus(200)
            ->assertJson(['error' => false]);

        $this->assertCount(2, $response['data']);
    }

    public function test_index_filters_by_match_type(): void
    {
        PriceRule::factory()->count(3)->create(['match_type' => 'all']);
        PriceRule::factory()->count(2)->create(['match_type' => 'any']);

        $response = $this->actingAs($this->user)->getJson('/api/admin/price-rules?match_type=all');

        $response->assertStatus(200)
            ->assertJson(['error' => false]);

        $this->assertCount(3, $response['data']);
    }

    public function test_index_filters_by_action_mode(): void
    {
        PriceRule::factory()->count(2)->create(['action_mode' => ActionMode::PERCENTAGE->value]);
        PriceRule::factory()->count(3)->create(['action_mode' => ActionMode::ABSOLUTE->value]);

        $response = $this->actingAs($this->user)->getJson('/api/admin/price-rules?action_mode=percentage');

        $response->assertStatus(200)
            ->assertJson(['error' => false]);

        $this->assertCount(2, $response['data']);
    }

    public function test_index_with_pagination(): void
    {
        PriceRule::factory()->count(25)->create();

        $response = $this->actingAs($this->user)->getJson('/api/admin/price-rules?per_page=10');

        $response->assertStatus(200)
            ->assertJson(['error' => false])
            ->assertJsonCount(10, 'data');
    }

    public function test_store_creates_price_rule(): void
    {
        $product = Product::factory()->create(['brand_id' => $this->brand->id]);

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
                    'field' => 'brand_id',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $this->brand->id,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->postJson('/api/admin/price-rules', $data);

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
            'field' => 'brand_id',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $data = [
            'name' => 'Incomplete Rule',
            // Missing other required fields
        ];

        $response = $this->actingAs($this->user)->postJson('/api/admin/price-rules', $data);

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

        $response = $this->actingAs($this->user)->postJson('/api/admin/price-rules', $data);

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

        $response = $this->actingAs($this->user)->postJson('/api/admin/price-rules', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['conditions']);
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

        $response = $this->actingAs($this->user)->getJson("/api/admin/price-rules/{$priceRule->id}");

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
        $response = $this->actingAs($this->user)->getJson('/api/admin/price-rules/999');

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
                    'field' => 'brand_id',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $this->brand->id,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->putJson("/api/admin/price-rules/{$priceRule->id}", $data);

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
                    'field' => 'brand_id',
                    'operator' => Operator::EQUAL->value,
                    'value' => (string) $this->brand->id,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->putJson("/api/admin/price-rules/{$priceRule->id}", $data);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('price_rule_conditions', [
            'price_rule_id' => $priceRule->id,
            'field' => 'old_field',
        ]);

        $this->assertDatabaseHas('price_rule_conditions', [
            'price_rule_id' => $priceRule->id,
            'field' => 'brand_id',
        ]);
    }

    public function test_destroy_deletes_price_rule(): void
    {
        $priceRule = PriceRule::factory()->create();
        $priceRule->conditions()->create([
            'field' => 'brand_id',
            'operator' => Operator::EQUAL->value,
            'value' => '5',
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/admin/price-rules/{$priceRule->id}");

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
        $response = $this->actingAs($this->user)->deleteJson('/api/admin/price-rules/999');

        $response->assertStatus(404);
    }
}
