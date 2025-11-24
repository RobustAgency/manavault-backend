<?php

namespace Tests\Feature\Repositories;

use Tests\TestCase;
use App\Models\Brand;
use App\Models\Product;
use App\Enums\Product\Lifecycle;
use App\Repositories\ProductRepository;
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
}
