<?php

namespace Tests\Feature\Repositories;

use App\Repositories\ProductRepository;
use App\Models\Product;
use App\Enums\Product\Lifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ProductRepositoryTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private ProductRepository $repository;

    public function setUp(): void
    {
        parent::setUp();
        $this->repository = app(ProductRepository::class);
    }

    public function test_create_product(): void
    {
        $data = [
            'name' => $this->faker->word(),
            'sku' => $this->faker->unique()->bothify('SKU-####'),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 1, 100),
            'status' => $this->faker->randomElement(array_map(fn($c) => $c->value, Lifecycle::cases())),
        ];

        $product = $this->repository->createProduct($data);

        $this->assertInstanceOf(Product::class, $product);
        $this->assertDatabaseHas('products', [
            'name' => $data['name'],
            'sku' => $data['sku'],
            'description' => $data['description'],
            'price' => $data['price'],
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

    public function test_update_product(): void
    {
        $product = Product::factory()->create();

        $updateData = [
            'name' => 'Updated Name',
            'sku' => 'Updated-SKU-1234',
            'description' => 'Updated description',
            'price' => 150.00,
        ];

        $updatedProduct = $this->repository->updateProduct($product, $updateData);

        $this->assertEquals('Updated Name', $updatedProduct->name);
        $this->assertEquals('Updated-SKU-1234', $updatedProduct->sku);
        $this->assertEquals('Updated description', $updatedProduct->description);
        $this->assertEquals(150.00, $updatedProduct->price);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Name',
            'sku' => 'Updated-SKU-1234',
            'description' => 'Updated description',
            'price' => 150.00,
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
