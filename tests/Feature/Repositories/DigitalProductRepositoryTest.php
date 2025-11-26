<?php

namespace Tests\Feature\Repositories;

use Tests\TestCase;
use App\Models\Supplier;
use App\Models\DigitalProduct;
use Illuminate\Foundation\Testing\WithFaker;
use App\Repositories\DigitalProductRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DigitalProductRepositoryTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private DigitalProductRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(DigitalProductRepository::class);
    }

    public function test_create_digital_product(): void
    {
        $supplier = Supplier::factory()->create();

        $data = [
            'supplier_id' => $supplier->id,
            'name' => $this->faker->word(),
            'sku' => $this->faker->unique()->regexify('[A-Z]{3}-[0-9]{5}'),
            'brand' => $this->faker->company(),
            'description' => $this->faker->sentence(),
            'cost_price' => $this->faker->randomFloat(2, 10, 100),
            'metadata' => ['external_id' => $this->faker->uuid()],
        ];

        $digitalProduct = $this->repository->createDigitalProduct($data);

        $this->assertInstanceOf(DigitalProduct::class, $digitalProduct);
        $this->assertDatabaseHas('digital_products', [
            'name' => $data['name'],
            'brand' => $data['brand'],
            'cost_price' => $data['cost_price'],
        ]);
    }

    public function test_create_bulk_digital_products(): void
    {
        $supplier = Supplier::factory()->create();

        $productsData = [
            [
                'supplier_id' => $supplier->id,
                'name' => 'Product 1',
                'sku' => 'SKU-001',
                'brand' => 'Brand A',
                'cost_price' => 10.00,
            ],
            [
                'supplier_id' => $supplier->id,
                'name' => 'Product 2',
                'sku' => 'SKU-002',
                'brand' => 'Brand B',
                'cost_price' => 20.00,
            ],
            [
                'supplier_id' => $supplier->id,
                'name' => 'Product 3',
                'sku' => 'SKU-003',
                'brand' => 'Brand C',
                'cost_price' => 30.00,
            ],
        ];

        $digitalProducts = $this->repository->createBulkDigitalProducts($productsData);

        $this->assertCount(3, $digitalProducts);
        $this->assertDatabaseCount('digital_products', 3);
        $this->assertDatabaseHas('digital_products', ['name' => 'Product 1']);
        $this->assertDatabaseHas('digital_products', ['name' => 'Product 2']);
        $this->assertDatabaseHas('digital_products', ['name' => 'Product 3']);
    }

    public function test_get_filtered_digital_products(): void
    {
        $supplier1 = Supplier::factory()->create();
        $supplier2 = Supplier::factory()->create();

        DigitalProduct::factory()->count(5)->create([
            'supplier_id' => $supplier1->id,
            'name' => 'Steam Gift Card',
        ]);
        DigitalProduct::factory()->count(3)->create([
            'supplier_id' => $supplier2->id,
            'name' => 'PlayStation Card',
        ]);

        $allProducts = $this->repository->getFilteredDigitalProducts();
        $this->assertCount(8, $allProducts->items());

        $filteredByName = $this->repository->getFilteredDigitalProducts(['name' => 'Steam']);
        $this->assertCount(5, $filteredByName->items());

        $filteredBySupplier = $this->repository->getFilteredDigitalProducts(['supplier_id' => $supplier1->id]);
        $this->assertCount(5, $filteredBySupplier->items());
    }

    public function test_get_filtered_digital_products_by_brand(): void
    {
        DigitalProduct::factory()->create(['brand' => 'Apple']);
        DigitalProduct::factory()->create(['brand' => 'Samsung']);
        DigitalProduct::factory()->create(['brand' => 'Apple Inc']);

        $results = $this->repository->getFilteredDigitalProducts(['brand' => 'Apple']);

        $this->assertCount(2, $results->items());
    }

    public function test_update_digital_product(): void
    {
        $digitalProduct = DigitalProduct::factory()->create([
            'name' => 'Original Name',
            'cost_price' => 10.00,
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'brand' => 'Updated Brand',
            'cost_price' => 25.00,
        ];

        $updatedProduct = $this->repository->updateDigitalProduct($digitalProduct, $updateData);

        $this->assertEquals('Updated Name', $updatedProduct->name);
        $this->assertEquals('Updated Brand', $updatedProduct->brand);
        $this->assertEquals(25.00, $updatedProduct->cost_price);

        $this->assertDatabaseHas('digital_products', [
            'id' => $digitalProduct->id,
            'name' => 'Updated Name',
            'cost_price' => 25.00,
        ]);
    }

    public function test_delete_digital_product(): void
    {
        $digitalProduct = DigitalProduct::factory()->create();

        $result = $this->repository->deleteDigitalProduct($digitalProduct);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('digital_products', [
            'id' => $digitalProduct->id,
        ]);
    }

    public function test_get_by_supplier(): void
    {
        $supplier1 = Supplier::factory()->create();
        $supplier2 = Supplier::factory()->create();

        DigitalProduct::factory()->count(3)->create(['supplier_id' => $supplier1->id]);
        DigitalProduct::factory()->count(2)->create(['supplier_id' => $supplier2->id]);

        $supplier1Products = $this->repository->getBySupplier($supplier1->id);
        $supplier2Products = $this->repository->getBySupplier($supplier2->id);

        $this->assertCount(3, $supplier1Products);
        $this->assertCount(2, $supplier2Products);
    }
}
