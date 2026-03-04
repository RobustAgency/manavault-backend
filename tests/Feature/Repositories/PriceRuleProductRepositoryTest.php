<?php

namespace Tests\Feature\Repositories;

use Tests\TestCase;
use App\Models\Product;
use App\Models\PriceRule;
use App\Models\PriceRuleProduct;
use App\Repositories\PriceRuleProductRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PriceRuleProductRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private PriceRuleProductRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(PriceRuleProductRepository::class);
    }

    public function test_create_stores_record_and_returns_model(): void
    {
        $product = Product::factory()->create();
        $priceRule = PriceRule::factory()->create();

        $data = [
            'product_id' => $product->id,
            'price_rule_id' => $priceRule->id,
            'original_selling_price' => 50.00,
            'base_value' => 50.00,
            'action_mode' => 'percentage',
            'action_operator' => 'subtract',
            'action_value' => 10.00,
            'calculated_price' => 45.00,
            'final_selling_price' => 45.00,
            'applied_at' => now(),
        ];

        $record = $this->repository->create($data);

        $this->assertInstanceOf(PriceRuleProduct::class, $record);
        $this->assertEquals($product->id, $record->product_id);
        $this->assertEquals($priceRule->id, $record->price_rule_id);
        $this->assertEquals(50.00, $record->original_selling_price);
        $this->assertEquals(45.00, $record->final_selling_price);
        $this->assertDatabaseHas('price_rule_product', [
            'product_id' => $product->id,
            'price_rule_id' => $priceRule->id,
            'final_selling_price' => 45.00,
        ]);
    }

    public function test_create_with_absolute_action_mode(): void
    {
        $product = Product::factory()->create(['face_value' => 100.00, 'selling_price' => 100.00]);
        $priceRule = PriceRule::factory()->create();

        $record = $this->repository->create([
            'product_id' => $product->id,
            'price_rule_id' => $priceRule->id,
            'original_selling_price' => 100.00,
            'base_value' => 100.00,
            'action_mode' => 'fixed',
            'action_operator' => 'add',
            'action_value' => 20.00,
            'calculated_price' => 120.00,
            'final_selling_price' => 120.00,
            'applied_at' => now(),
        ]);

        $this->assertEquals('fixed', $record->action_mode);
        $this->assertEquals('add', $record->action_operator);
        $this->assertEquals(120.00, $record->final_selling_price);
    }

    public function test_get_by_price_rule_returns_only_matching_records(): void
    {
        $priceRule = PriceRule::factory()->create();
        $otherRule = PriceRule::factory()->create();

        PriceRuleProduct::factory()->count(3)->create(['price_rule_id' => $priceRule->id]);
        PriceRuleProduct::factory()->count(2)->create(['price_rule_id' => $otherRule->id]);

        $result = $this->repository->getByPriceRule($priceRule->id);

        $this->assertCount(3, $result->items());
        foreach ($result->items() as $item) {
            $this->assertEquals($priceRule->id, $item->price_rule_id);
        }
    }

    public function test_get_by_price_rule_eager_loads_product_relation(): void
    {
        $priceRule = PriceRule::factory()->create();
        PriceRuleProduct::factory()->create(['price_rule_id' => $priceRule->id]);

        $result = $this->repository->getByPriceRule($priceRule->id);

        $this->assertTrue($result->items()[0]->relationLoaded('product'));
        $this->assertInstanceOf(Product::class, $result->items()[0]->product);
    }

    public function test_get_by_price_rule_orders_by_applied_at_descending(): void
    {
        $priceRule = PriceRule::factory()->create();

        PriceRuleProduct::factory()->create(['price_rule_id' => $priceRule->id, 'applied_at' => now()->subDays(2)]);
        PriceRuleProduct::factory()->create(['price_rule_id' => $priceRule->id, 'applied_at' => now()->subDays(1)]);
        PriceRuleProduct::factory()->create(['price_rule_id' => $priceRule->id, 'applied_at' => now()]);

        $result = $this->repository->getByPriceRule($priceRule->id);
        $items = $result->items();

        $this->assertTrue($items[0]->applied_at >= $items[1]->applied_at);
        $this->assertTrue($items[1]->applied_at >= $items[2]->applied_at);
    }

    public function test_get_by_price_rule_returns_paginator(): void
    {
        $priceRule = PriceRule::factory()->create();
        PriceRuleProduct::factory()->count(20)->create(['price_rule_id' => $priceRule->id]);

        $result = $this->repository->getByPriceRule($priceRule->id, perPage: 5);

        $this->assertCount(5, $result->items());
        $this->assertEquals(20, $result->total());
        $this->assertEquals(4, $result->lastPage());
    }

    public function test_get_by_price_rule_returns_empty_for_unknown_id(): void
    {
        $result = $this->repository->getByPriceRule(99999);

        $this->assertCount(0, $result->items());
        $this->assertEquals(0, $result->total());
    }

    public function test_get_by_product_returns_only_matching_records(): void
    {
        $product = Product::factory()->create();
        $otherProduct = Product::factory()->create();

        PriceRuleProduct::factory()->count(4)->create(['product_id' => $product->id]);
        PriceRuleProduct::factory()->count(2)->create(['product_id' => $otherProduct->id]);

        $result = $this->repository->getByProduct($product->id);

        $this->assertCount(4, $result->items());
        foreach ($result->items() as $item) {
            $this->assertEquals($product->id, $item->product_id);
        }
    }

    public function test_get_by_product_eager_loads_price_rule_relation(): void
    {
        $product = Product::factory()->create();
        PriceRuleProduct::factory()->create(['product_id' => $product->id]);

        $result = $this->repository->getByProduct($product->id);

        $this->assertTrue($result->items()[0]->relationLoaded('priceRule'));
        $this->assertInstanceOf(PriceRule::class, $result->items()[0]->priceRule);
    }

    public function test_get_by_product_returns_paginator(): void
    {
        $product = Product::factory()->create();
        PriceRuleProduct::factory()->count(10)->create(['product_id' => $product->id]);

        $result = $this->repository->getByProduct($product->id, perPage: 3);

        $this->assertCount(3, $result->items());
        $this->assertEquals(10, $result->total());
    }

    public function test_get_filtered_returns_all_records_without_filters(): void
    {
        PriceRuleProduct::factory()->count(5)->create();

        $result = $this->repository->getFilteredPriceRuleProducts();

        $this->assertEquals(5, $result->total());
    }

    public function test_get_filtered_by_price_rule_id(): void
    {
        $priceRule = PriceRule::factory()->create();
        $otherRule = PriceRule::factory()->create();

        PriceRuleProduct::factory()->count(3)->create(['price_rule_id' => $priceRule->id]);
        PriceRuleProduct::factory()->count(2)->create(['price_rule_id' => $otherRule->id]);

        $result = $this->repository->getFilteredPriceRuleProducts(['price_rule_id' => $priceRule->id]);

        $this->assertCount(3, $result->items());
        foreach ($result->items() as $item) {
            $this->assertEquals($priceRule->id, $item->price_rule_id);
        }
    }

    public function test_get_filtered_by_product_id(): void
    {
        $product = Product::factory()->create();
        $otherProduct = Product::factory()->create();

        PriceRuleProduct::factory()->count(2)->create(['product_id' => $product->id]);
        PriceRuleProduct::factory()->count(4)->create(['product_id' => $otherProduct->id]);

        $result = $this->repository->getFilteredPriceRuleProducts(['product_id' => $product->id]);

        $this->assertCount(2, $result->items());
        foreach ($result->items() as $item) {
            $this->assertEquals($product->id, $item->product_id);
        }
    }

    public function test_get_filtered_with_combined_filters(): void
    {
        $priceRule = PriceRule::factory()->create();
        $product = Product::factory()->create();

        PriceRuleProduct::factory()->create([
            'price_rule_id' => $priceRule->id,
            'product_id' => $product->id,
            'applied_at' => now()->subDay(),
        ]);
        // Same rule, different product
        PriceRuleProduct::factory()->create([
            'price_rule_id' => $priceRule->id,
            'applied_at' => now()->subDay(),
        ]);
        // Different rule, same product
        PriceRuleProduct::factory()->create([
            'product_id' => $product->id,
            'applied_at' => now()->subDay(),
        ]);

        $result = $this->repository->getFilteredPriceRuleProducts([
            'price_rule_id' => $priceRule->id,
            'product_id' => $product->id,
        ]);

        $this->assertCount(1, $result->items());
        $this->assertEquals($priceRule->id, $result->items()[0]->price_rule_id);
        $this->assertEquals($product->id, $result->items()[0]->product_id);
    }

    public function test_get_filtered_eager_loads_product_and_price_rule(): void
    {
        PriceRuleProduct::factory()->create();

        $result = $this->repository->getFilteredPriceRuleProducts();

        $item = $result->items()[0];
        $this->assertTrue($item->relationLoaded('product'));
        $this->assertTrue($item->relationLoaded('priceRule'));
        $this->assertInstanceOf(Product::class, $item->product);
        $this->assertInstanceOf(PriceRule::class, $item->priceRule);
    }

    public function test_find_by_id_returns_correct_record(): void
    {
        $record = PriceRuleProduct::factory()->create();

        $found = $this->repository->findById($record->id);

        $this->assertInstanceOf(PriceRuleProduct::class, $found);
        $this->assertEquals($record->id, $found->id);
    }

    public function test_find_by_id_eager_loads_product_and_price_rule(): void
    {
        $record = PriceRuleProduct::factory()->create();

        $found = $this->repository->findById($record->id);

        $this->assertNotNull($found);
        $this->assertTrue($found->relationLoaded('product'));
        $this->assertTrue($found->relationLoaded('priceRule'));
        $this->assertInstanceOf(Product::class, $found->product);
        $this->assertInstanceOf(PriceRule::class, $found->priceRule);
    }

    public function test_delete_by_price_rule_id_removes_only_matching_records(): void
    {
        $priceRule = PriceRule::factory()->create();
        $otherRule = PriceRule::factory()->create();

        PriceRuleProduct::factory()->count(3)->create(['price_rule_id' => $priceRule->id]);
        PriceRuleProduct::factory()->count(2)->create(['price_rule_id' => $otherRule->id]);

        $this->repository->deleteByPriceRuleId($priceRule->id);

        $this->assertDatabaseMissing('price_rule_product', ['price_rule_id' => $priceRule->id]);
        $this->assertEquals(2, PriceRuleProduct::where('price_rule_id', $otherRule->id)->count());
    }

    public function test_delete_by_price_rule_id_with_no_records_does_not_throw(): void
    {
        $priceRule = PriceRule::factory()->create();

        // Should not throw when there are no records to delete
        $this->repository->deleteByPriceRuleId($priceRule->id);

        $this->assertDatabaseCount('price_rule_product', 0);
    }

    public function test_delete_by_price_rule_id_removes_all_records_for_rule(): void
    {
        $priceRule = PriceRule::factory()->create();
        PriceRuleProduct::factory()->count(5)->create(['price_rule_id' => $priceRule->id]);

        $this->assertEquals(5, PriceRuleProduct::where('price_rule_id', $priceRule->id)->count());

        $this->repository->deleteByPriceRuleId($priceRule->id);

        $this->assertEquals(0, PriceRuleProduct::where('price_rule_id', $priceRule->id)->count());
    }
}
