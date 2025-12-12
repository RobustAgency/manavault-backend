<?php

namespace Tests\Feature\Repositories;

use Tests\TestCase;
use App\Models\PriceRule;
use App\Models\PriceRuleCondition;
use App\Repositories\PriceRuleConditionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PriceRuleConditionRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private PriceRuleConditionRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(PriceRuleConditionRepository::class);
    }

    public function test_create_condition(): void
    {
        $priceRule = PriceRule::factory()->create();

        $data = [
            'price_rule_id' => $priceRule->id,
            'field' => 'brand_id',
            'operator' => '=',
            'value' => '5',
        ];

        $condition = $this->repository->create($data);

        $this->assertInstanceOf(PriceRuleCondition::class, $condition);
        $this->assertEquals('brand_id', $condition->field);
        $this->assertEquals('=', $condition->operator);
        $this->assertEquals('5', $condition->value);
        $this->assertEquals($priceRule->id, $condition->price_rule_id);
        $this->assertDatabaseHas('price_rule_conditions', [
            'price_rule_id' => $priceRule->id,
            'field' => 'brand_id',
            'operator' => '=',
            'value' => '5',
        ]);
    }

    public function test_create_condition_with_different_operators(): void
    {
        $priceRule = PriceRule::factory()->create();

        $operators = ['=', '!=', '>', '<', '>=', '<=', 'contains'];

        foreach ($operators as $operator) {
            $data = [
                'price_rule_id' => $priceRule->id,
                'field' => 'selling_price',
                'operator' => $operator,
                'value' => '100',
            ];

            $condition = $this->repository->create($data);

            $this->assertEquals($operator, $condition->operator);
        }
    }

    public function test_create_condition_with_different_fields(): void
    {
        $priceRule = PriceRule::factory()->create();

        $fields = ['brand_id', 'selling_price', 'name', 'status', 'sku'];

        foreach ($fields as $field) {
            $data = [
                'price_rule_id' => $priceRule->id,
                'field' => $field,
                'operator' => '=',
                'value' => 'test_value',
            ];

            $condition = $this->repository->create($data);

            $this->assertEquals($field, $condition->field);
        }
    }

    public function test_create_multiple_conditions_for_same_rule(): void
    {
        $priceRule = PriceRule::factory()->create();

        $conditions = [
            [
                'price_rule_id' => $priceRule->id,
                'field' => 'brand_id',
                'operator' => '=',
                'value' => '5',
            ],
            [
                'price_rule_id' => $priceRule->id,
                'field' => 'selling_price',
                'operator' => '>',
                'value' => '100',
            ],
            [
                'price_rule_id' => $priceRule->id,
                'field' => 'name',
                'operator' => 'contains',
                'value' => 'Premium',
            ],
        ];

        foreach ($conditions as $conditionData) {
            $this->repository->create($conditionData);
        }

        $this->assertCount(3, $priceRule->conditions);
        $this->assertDatabaseCount('price_rule_conditions', 3);
    }

    public function test_delete_conditions_by_price_rule(): void
    {
        $priceRule = PriceRule::factory()->create();
        $otherPriceRule = PriceRule::factory()->create();

        // Create conditions for first rule
        $this->repository->create([
            'price_rule_id' => $priceRule->id,
            'field' => 'brand_id',
            'operator' => '=',
            'value' => '5',
        ]);
        $this->repository->create([
            'price_rule_id' => $priceRule->id,
            'field' => 'selling_price',
            'operator' => '>',
            'value' => '100',
        ]);

        // Create conditions for second rule
        $this->repository->create([
            'price_rule_id' => $otherPriceRule->id,
            'field' => 'name',
            'operator' => 'contains',
            'value' => 'Sale',
        ]);

        // Delete conditions for first rule
        $this->repository->deleteConditionsByPriceRule($priceRule);

        // Verify that only the other rule's conditions remain
        $this->assertDatabaseMissing('price_rule_conditions', [
            'price_rule_id' => $priceRule->id,
        ]);
        $this->assertDatabaseHas('price_rule_conditions', [
            'price_rule_id' => $otherPriceRule->id,
        ]);
        $this->assertDatabaseCount('price_rule_conditions', 1);
    }

    public function test_delete_conditions_deletes_all_conditions_for_rule(): void
    {
        $priceRule = PriceRule::factory()->create();

        // Create multiple conditions
        for ($i = 0; $i < 5; $i++) {
            $this->repository->create([
                'price_rule_id' => $priceRule->id,
                'field' => "field_{$i}",
                'operator' => '=',
                'value' => "value_{$i}",
            ]);
        }

        $this->assertDatabaseCount('price_rule_conditions', 5);

        // Delete all conditions for the rule
        $this->repository->deleteConditionsByPriceRule($priceRule);

        $this->assertDatabaseCount('price_rule_conditions', 0);
    }

    public function test_delete_conditions_for_rule_with_no_conditions(): void
    {
        $priceRule = PriceRule::factory()->create();

        // Should not throw an error when deleting conditions for a rule with no conditions
        $this->repository->deleteConditionsByPriceRule($priceRule);

        $this->assertDatabaseCount('price_rule_conditions', 0);
    }

    public function test_condition_relationship_with_price_rule(): void
    {
        $priceRule = PriceRule::factory()->create(['name' => 'Test Rule']);

        $condition = $this->repository->create([
            'price_rule_id' => $priceRule->id,
            'field' => 'brand_id',
            'operator' => '=',
            'value' => '5',
        ]);

        // Refresh to ensure the relationship is loaded
        $condition->refresh();

        $this->assertInstanceOf(PriceRule::class, $condition->priceRule);
        $this->assertEquals('Test Rule', $condition->priceRule->name);
    }
}
