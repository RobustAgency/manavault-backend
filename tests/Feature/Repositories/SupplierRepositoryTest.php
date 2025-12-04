<?php

namespace Tests\Feature\Repositories;

use Tests\TestCase;
use App\Models\Supplier;
use App\Enums\SupplierType;
use App\Repositories\SupplierRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SupplierRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private SupplierRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new SupplierRepository;
    }

    public function test_create_a_supplier()
    {
        $data = [
            'name' => 'Test Supplier',
            'contact_email' => 'test@example.com',
            'contact_phone' => '1234567890',
            'status' => 'active',
        ];

        $supplier = $this->repository->createSupplier($data);

        $this->assertInstanceOf(Supplier::class, $supplier);
        $this->assertEquals('Test Supplier', $supplier->name);
        $this->assertEquals('test_supplier', $supplier->slug);
        $this->assertEquals('internal', $supplier->type);
        $this->assertDatabaseHas('suppliers', [
            'name' => 'Test Supplier',
            'slug' => 'test_supplier',
            'type' => SupplierType::INTERNAL->value,
        ]);
    }

    public function test_update_a_supplier()
    {
        $supplier = Supplier::factory()->create(['name' => 'Old Name']);

        $updated = $this->repository->updateSupplier($supplier, [
            'name' => 'New Name',
            'status' => 'inactive',
        ]);

        $this->assertTrue($updated);
        $supplier->refresh();
        $this->assertEquals('New Name', $supplier->name);
        $this->assertEquals('inactive', $supplier->status);
    }

    public function test_delete_a_supplier()
    {
        $supplier = Supplier::factory()->create();

        $deleted = $this->repository->deleteSupplier($supplier);

        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }

    public function it_can_get_filtered_suppliers()
    {
        Supplier::factory()->create(['name' => 'Alpha Supplier', 'type' => 'internal', 'status' => 'active']);
        Supplier::factory()->create(['name' => 'Beta Supplier', 'type' => 'external', 'status' => 'active']);
        Supplier::factory()->create(['name' => 'Gamma Supplier', 'type' => 'internal', 'status' => 'inactive']);

        $results = $this->repository->getFilteredSuppliers(['per_page' => 10]);

        $this->assertEquals(3, $results->total());
    }

    public function test_filter_suppliers_by_name()
    {
        Supplier::factory()->create(['name' => 'Alpha Supplier']);
        Supplier::factory()->create(['name' => 'Beta Supplier']);

        $results = $this->repository->getFilteredSuppliers(['name' => 'Alpha']);

        $this->assertEquals(1, $results->total());
        $this->assertEquals('Alpha Supplier', $results->first()->name);
    }

    public function test_filter_suppliers_by_type()
    {
        Supplier::factory()->create(['type' => 'internal']);
        Supplier::factory()->create(['type' => 'external']);
        Supplier::factory()->create(['type' => 'internal']);

        $results = $this->repository->getFilteredSuppliers(['type' => 'external']);

        $this->assertEquals(1, $results->total());
    }

    public function test_filter_suppliers_by_status()
    {
        Supplier::factory()->create(['status' => 'active']);
        Supplier::factory()->create(['status' => 'inactive']);
        Supplier::factory()->create(['status' => 'active']);

        $results = $this->repository->getFilteredSuppliers(['status' => 'active']);

        $this->assertEquals(2, $results->total());
    }
}
