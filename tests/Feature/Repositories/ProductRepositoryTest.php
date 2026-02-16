<?php

namespace Tests\Feature\Repositories;

use Tests\TestCase;
use App\Models\Brand;
use App\Models\Product;
use App\Enums\Product\Lifecycle;
use App\Repositories\ProductRepository;
use App\Enums\PriceRuleCondition\Operator;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductRepositoryTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private ProductRepository $repository;

    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(ProductRepository::class);
        $this->brand = Brand::factory()->create();
    }

    public function test_create_product(): void
    {
        $data = [
            'name' => $this->faker->word(),
            'sku' => $this->faker->unique()->bothify('SKU-####'),
            'brand_id' => $this->brand->id,
            'description' => $this->faker->sentence(),
            'short_description' => $this->faker->sentence(10),
            'long_description' => $this->faker->paragraph(),
            'tags' => ['gaming', 'gift card'],
            'image' => $this->faker->imageUrl(),
            'selling_price' => $this->faker->randomFloat(2, 1, 100),
            'status' => $this->faker->randomElement(array_map(fn ($c) => $c->value, Lifecycle::cases())),
            'regions' => ['US', 'CA'],
        ];

        $product = $this->repository->createProduct($data);

        $this->assertInstanceOf(Product::class, $product);
        $this->assertDatabaseHas('products', [
            'name' => $data['name'],
            'sku' => $data['sku'],
            'brand_id' => $data['brand_id'],
            'description' => $data['description'],
            'selling_price' => $data['selling_price'],
            'status' => $data['status'],
        ]);
    }

    public function test_get_filtered_products(): void
    {
        Product::factory()->count(5)->create(['name' => 'Jet to Holiday']);
        Product::factory()->count(3)->create(['name' => '20% Off Sale']);

        $activeProducts = $this->repository->getFilteredProducts(['name' => 'Jet to Holiday']);
        $allProducts = $this->repository->getFilteredProducts();

        $this->assertCount(5, $activeProducts->items());
        $this->assertCount(8, $allProducts->items());
    }

    public function test_get_filtered_products_by_name(): void
    {
        Product::factory()->create(['name' => 'Test Product']);
        Product::factory()->create(['name' => 'Another Product']);
        Product::factory()->create(['name' => 'Different Item']);

        $results = $this->repository->getFilteredProducts(['name' => 'Product']);

        $this->assertCount(2, $results->items());
    }

    public function test_get_filtered_products_by_status(): void
    {
        Product::factory()->create(['status' => Lifecycle::ACTIVE->value]);
        Product::factory()->create(['status' => Lifecycle::IN_ACTIVE->value]);
        Product::factory()->create(['status' => Lifecycle::ACTIVE->value]);

        $results = $this->repository->getFilteredProducts(['status' => Lifecycle::ACTIVE->value]);

        $this->assertCount(2, $results->items());
    }

    public function test_get_filtered_products_by_brand(): void
    {
        $brand1 = Brand::factory()->create(['name' => 'Apple']);
        $brand2 = Brand::factory()->create(['name' => 'Samsung']);
        Product::factory()->create(['brand_id' => $brand1->id]);
        Product::factory()->create(['brand_id' => $brand2->id]);
        Product::factory()->create(['brand_id' => $brand1->id]);

        $results = $this->repository->getFilteredProducts(['brand_id' => $brand1->id]);

        $this->assertCount(2, $results->items());
    }

    public function test_update_product(): void
    {
        $product = Product::factory()->create();

        $updateData = [
            'name' => 'Updated Name',
            'sku' => 'Updated-SKU-1234',
            'brand_id' => $this->brand->id,
            'description' => 'Updated description',
            'short_description' => 'Updated short description',
            'long_description' => 'Updated long description',
            'tags' => ['updated', 'tags'],
            'image' => 'https://example.com/updated-image.jpg',
            'selling_price' => 150.00,
            'regions' => ['UK', 'EU'],
        ];

        $updatedProduct = $this->repository->updateProduct($product, $updateData);

        $this->assertEquals('Updated Name', $updatedProduct->name);
        $this->assertEquals('Updated-SKU-1234', $updatedProduct->sku);
        $this->assertEquals($this->brand->id, $updatedProduct->brand_id);
        $this->assertEquals('Updated description', $updatedProduct->description);
        $this->assertEquals(150.00, $updatedProduct->selling_price);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Name',
            'sku' => 'Updated-SKU-1234',
            'brand_id' => $this->brand->id,
            'description' => 'Updated description',
            'selling_price' => 150.00,
        ]);
    }

    public function test_delete_product(): void
    {
        $product = Product::factory()->create();

        $result = $this->repository->deleteProduct($product);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
        ]);
    }

    public function test_get_products_by_conditions_with_single_equal_condition(): void
    {
        Product::factory()->create(['name' => 'Gaming Gift Card']);
        Product::factory()->create(['name' => 'Sports Gift Card']);
        Product::factory()->create(['name' => 'Gaming Gift Card']);

        $conditions = [
            ['field' => 'name', 'operator' => Operator::EQUAL->value, 'value' => 'Gaming Gift Card'],
        ];

        $results = $this->repository->getProductsByConditions($conditions);

        $this->assertCount(2, $results);
        $this->assertTrue($results->every(fn ($product) => $product->name === 'Gaming Gift Card'));
    }

    public function test_get_products_by_conditions_with_price_greater_than(): void
    {
        Product::factory()->create(['selling_price' => 50.00]);
        Product::factory()->create(['selling_price' => 100.00]);
        Product::factory()->create(['selling_price' => 150.00]);

        $conditions = [
            ['field' => 'selling_price', 'operator' => Operator::GREATER_THAN->value, 'value' => '75.00'],
        ];

        $results = $this->repository->getProductsByConditions($conditions);

        $this->assertCount(2, $results);
        $this->assertTrue($results->every(fn ($product) => $product->selling_price > 75.00));
    }

    public function test_get_products_by_conditions_with_multiple_conditions_all_match(): void
    {
        $brand1 = Brand::factory()->create();
        $brand2 = Brand::factory()->create();

        Product::factory()->create([
            'name' => 'Premium Gaming Card',
            'brand_id' => $brand1->id,
            'selling_price' => 100.00,
        ]);
        Product::factory()->create([
            'name' => 'Budget Gaming Card',
            'brand_id' => $brand1->id,
            'selling_price' => 25.00,
        ]);
        Product::factory()->create([
            'name' => 'Premium Sports Card',
            'brand_id' => $brand2->id,
            'selling_price' => 100.00,
        ]);

        $conditions = [
            ['field' => 'brand_id', 'operator' => Operator::EQUAL->value, 'value' => (string) $brand1->id],
            ['field' => 'selling_price', 'operator' => Operator::GREATER_THAN_OR_EQUAL->value, 'value' => '100.00'],
        ];

        $results = $this->repository->getProductsByConditions($conditions, 'all');

        $this->assertCount(1, $results);
        $this->assertEquals('Premium Gaming Card', $results->first()->name);
    }

    public function test_get_products_by_conditions_with_multiple_conditions_any_match(): void
    {
        $brand1 = Brand::factory()->create();
        $brand2 = Brand::factory()->create();

        Product::factory()->create([
            'name' => 'Premium Gaming Card',
            'brand_id' => $brand1->id,
            'selling_price' => 100.00,
        ]);
        Product::factory()->create([
            'name' => 'Budget Card',
            'brand_id' => $brand2->id,
            'selling_price' => 25.00,
        ]);
        Product::factory()->create([
            'name' => 'Expensive Card',
            'brand_id' => $brand2->id,
            'selling_price' => 200.00,
        ]);

        $conditions = [
            ['field' => 'brand_id', 'operator' => Operator::EQUAL->value, 'value' => (string) $brand1->id],
            ['field' => 'selling_price', 'operator' => Operator::GREATER_THAN_OR_EQUAL->value, 'value' => '200.00'],
        ];

        $results = $this->repository->getProductsByConditions($conditions, 'any');

        $this->assertCount(2, $results);
        $this->assertTrue($results->contains('name', 'Premium Gaming Card'));
        $this->assertTrue($results->contains('name', 'Expensive Card'));
    }

    public function test_get_products_by_conditions_with_contains_operator(): void
    {
        Product::factory()->create(['name' => 'Apple iPhone Gift Card']);
        Product::factory()->create(['name' => 'Samsung Galaxy Card']);
        Product::factory()->create(['name' => 'Apple Watch Card']);

        $conditions = [
            ['field' => 'name', 'operator' => Operator::CONTAINS->value, 'value' => 'Apple'],
        ];

        $results = $this->repository->getProductsByConditions($conditions);

        $this->assertCount(2, $results);
        $this->assertTrue($results->every(fn ($product) => str_contains($product->name, 'Apple')));
    }

    public function test_get_products_by_conditions_with_no_matches(): void
    {
        Product::factory()->create(['selling_price' => 50.00]);
        Product::factory()->create(['selling_price' => 75.00]);

        $conditions = [
            ['field' => 'selling_price', 'operator' => Operator::GREATER_THAN->value, 'value' => '200.00'],
        ];

        $results = $this->repository->getProductsByConditions($conditions);

        $this->assertEmpty($results);
    }

    public function test_get_products_by_conditions_with_brand_name(): void
    {
        $brand1 = Brand::factory()->create(['name' => 'Apple INC LTD']);
        $brand2 = Brand::factory()->create(['name' => 'Samsung Electronics Co.']);

        Product::factory()->create([
            'name' => 'iPhone Gift Card',
            'brand_id' => $brand1->id,
        ]);
        Product::factory()->create([
            'name' => 'MacBook Gift Card',
            'brand_id' => $brand1->id,
        ]);
        Product::factory()->create([
            'name' => 'Samsung TV Card',
            'brand_id' => $brand2->id,
        ]);

        $conditions = [
            ['field' => 'brand_name', 'operator' => Operator::EQUAL->value, 'value' => 'Samsung Electronics Co.'],
        ];

        $results = $this->repository->getProductsByConditions($conditions);

        $this->assertCount(1, $results);
        $this->assertTrue($results->every(fn ($product) => $product->brand_id === $brand2->id));
    }

    public function test_product_quantity_zero_when_no_purchase_orders(): void
    {
        $product = Product::factory()->create();

        // No purchase orders created
        $this->assertEquals(0, $product->quantity);
    }
}
