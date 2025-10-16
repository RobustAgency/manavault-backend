<?php

namespace Tests\Feature\Repositories;

use App\Repositories\ProductRepository;
use App\Models\Product;
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

    public function test_create_product_batch(): void
    {
        $data = [
            'name' => $this->faker->word(),
            'sku' => $this->faker->unique()->bothify('SKU-####'),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->randomFloat(2, 1, 100),
            'supplier_id' => \App\Models\Supplier::factory()->create()->id,
            'purchase_price' => $this->faker->randomFloat(2, 1, 100),
            'quantity' => $this->faker->numberBetween(1, 100),
        ];

        $product = $this->repository->createProductBatch($data);

        $this->assertDatabaseHas('products', [
            'name' => $product['name'],
            'sku' => $product['sku'],
        ]);
        $this->assertDatabaseHas('product_batches', [
            'supplier_id' => $data['supplier_id'],
            'purchase_price' => $data['purchase_price'],
            'quantity' => $data['quantity'],
        ]);
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
}
