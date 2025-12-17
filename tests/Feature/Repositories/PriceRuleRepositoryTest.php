<?php

namespace Tests\Feature\Repositories;

use Tests\TestCase;
use App\Models\PriceRule;
use App\Repositories\PriceRuleRepository;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PriceRuleRepositoryTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private PriceRuleRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(PriceRuleRepository::class);
    }

    public function test_create_price_rule(): void
    {
        $data = [
            'name' => 'Premium Brand Discount',
            'description' => 'Apply 10% discount to premium brands',
            'match_type' => 'all',
            'action_mode' => 'percentage',
            'action_value' => 10.00,
            'action_operator' => 'subtract',
            'status' => 'active',
        ];

        $priceRule = $this->repository->createPriceRule($data);

        $this->assertInstanceOf(PriceRule::class, $priceRule);
        $this->assertEquals('Premium Brand Discount', $priceRule->name);
        $this->assertEquals('all', $priceRule->match_type);
        $this->assertEquals('percentage', $priceRule->action_mode);
        $this->assertEquals(10.00, $priceRule->action_value);
        $this->assertDatabaseHas('price_rules', [
            'name' => 'Premium Brand Discount',
            'match_type' => 'all',
            'action_mode' => 'percentage',
        ]);
    }

    public function test_get_filtered_price_rules(): void
    {
        PriceRule::factory()->count(5)->create(['name' => 'Brand Discount']);
        PriceRule::factory()->count(3)->create(['name' => 'Seasonal Sale']);

        $allRules = $this->repository->getFilteredPriceRules();
        $brandRules = $this->repository->getFilteredPriceRules(['name' => 'Brand Discount']);

        $this->assertCount(8, $allRules->items());
        $this->assertCount(5, $brandRules->items());
    }

    public function test_get_filtered_price_rules_by_name(): void
    {
        PriceRule::factory()->create(['name' => 'Premium Discount']);
        PriceRule::factory()->create(['name' => 'Budget Offer']);
        PriceRule::factory()->create(['name' => 'Premium Sale']);

        $results = $this->repository->getFilteredPriceRules(['name' => 'Premium']);

        $this->assertCount(2, $results->items());
    }

    public function test_get_filtered_price_rules_by_match_type(): void
    {
        PriceRule::factory()->count(3)->create(['match_type' => 'all']);
        PriceRule::factory()->count(2)->create(['match_type' => 'any']);

        $results = $this->repository->getFilteredPriceRules(['match_type' => 'all']);

        $this->assertCount(3, $results->items());
        $this->assertTrue($results->items()[0]->match_type === 'all');
    }

    public function test_get_filtered_price_rules_by_action_mode(): void
    {
        PriceRule::factory()->count(4)->create(['action_mode' => 'percentage']);
        PriceRule::factory()->count(2)->create(['action_mode' => 'fixed']);

        $results = $this->repository->getFilteredPriceRules(['action_mode' => 'percentage']);

        $this->assertCount(4, $results->items());
        $this->assertTrue($results->items()[0]->action_mode === 'percentage');
    }

    public function test_get_filtered_price_rules_by_status(): void
    {
        PriceRule::factory()->count(3)->create(['status' => 'active']);
        PriceRule::factory()->count(2)->create(['status' => 'draft']);
        PriceRule::factory()->count(1)->create(['status' => 'inactive']);

        $results = $this->repository->getFilteredPriceRules(['status' => 'active']);

        $this->assertCount(3, $results->items());
        $this->assertTrue($results->items()[0]->status === 'active');
    }

    public function test_get_filtered_price_rules_with_multiple_filters(): void
    {
        PriceRule::factory()->create([
            'name' => 'Brand Discount',
            'match_type' => 'all',
            'action_mode' => 'percentage',
        ]);
        PriceRule::factory()->create([
            'name' => 'Brand Discount',
            'match_type' => 'any',
            'action_mode' => 'fixed',
        ]);
        PriceRule::factory()->create([
            'name' => 'Other Rule',
            'match_type' => 'all',
            'action_mode' => 'percentage',
        ]);

        $results = $this->repository->getFilteredPriceRules([
            'name' => 'Brand Discount',
            'match_type' => 'all',
            'action_mode' => 'percentage',
        ]);

        $this->assertCount(1, $results->items());
        $this->assertEquals('Brand Discount', $results->items()[0]->name);
    }

    public function test_get_filtered_price_rules_with_pagination(): void
    {
        PriceRule::factory()->count(25)->create();

        $results = $this->repository->getFilteredPriceRules(['per_page' => 10]);

        $this->assertCount(10, $results->items());
        $this->assertEquals(3, $results->lastPage());
    }

    public function test_get_filtered_price_rules_ordered_by_latest(): void
    {
        $oldRule = PriceRule::factory()->create(['name' => 'Old Rule']);
        sleep(1);
        $newRule = PriceRule::factory()->create(['name' => 'New Rule']);

        $results = $this->repository->getFilteredPriceRules();

        $this->assertEquals('New Rule', $results->items()[0]->name);
        $this->assertEquals('Old Rule', $results->items()[1]->name);
    }

    public function test_update_price_rule(): void
    {
        $priceRule = PriceRule::factory()->create([
            'name' => 'Original Rule',
            'action_value' => 5.00,
            'status' => 'draft',
        ]);

        $updateData = [
            'name' => 'Updated Rule',
            'action_value' => 15.00,
            'status' => 'active',
        ];

        $updatedRule = $this->repository->updatePriceRule($priceRule, $updateData);

        $this->assertEquals('Updated Rule', $updatedRule->name);
        $this->assertEquals(15.00, $updatedRule->action_value);
        $this->assertEquals('active', $updatedRule->status);
        $this->assertDatabaseHas('price_rules', [
            'id' => $priceRule->id,
            'name' => 'Updated Rule',
            'action_value' => 15.00,
            'status' => 'active',
        ]);
    }

    public function test_delete_price_rule(): void
    {
        $priceRule = PriceRule::factory()->create();

        $this->repository->deletePriceRule($priceRule);

        $this->assertDatabaseMissing('price_rules', [
            'id' => $priceRule->id,
        ]);
    }
}
