<?php

namespace Tests\Feature\Repositories;

use App\Models\Supplier;
use App\Repositories\SupplierRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private SupplierRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(SupplierRepository::class);
    }

    public function test_get_paginated_suppliers(): void
    {
        $suppliers = Supplier::factory()->count(3)->create();

        $result = $this->repository->getPaginatedSuppliers();

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertCount(3, $result->items());
        $this->assertEquals(3, $result->total());
        $this->assertEquals(1, $result->currentPage());
        $this->assertEquals(10, $result->perPage());
    }

    public function test_returns_empty_paginated_result_when_no_suppliers_exist(): void
    {
        $result = $this->repository->getPaginatedSuppliers();

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertCount(0, $result->items());
        $this->assertEquals(0, $result->total());
        $this->assertEquals(1, $result->currentPage());
        $this->assertEquals(10, $result->perPage());
    }

    public function test_pagination_with_custom_per_page(): void
    {
        Supplier::factory()->count(15)->create();

        $result = $this->repository->getPaginatedSuppliers(5);

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertCount(5, $result->items());
        $this->assertEquals(15, $result->total());
        $this->assertEquals(1, $result->currentPage());
        $this->assertEquals(5, $result->perPage());
        $this->assertEquals(3, $result->lastPage());
    }

    public function test_create_a_new_supplier(): void
    {
        $data = [
            'name' => 'Test Company Ltd',
            'type' => 'external',
            'contact_email' => 'test@example.com',
            'contact_phone' => '+1234567890',
            'status' => 'active',
        ];

        $supplier = $this->repository->createSupplier($data);

        $this->assertInstanceOf(Supplier::class, $supplier);
        $this->assertTrue($supplier->exists);
        $this->assertEquals('Test Company Ltd', $supplier->name);
        $this->assertEquals('external', $supplier->type);
        $this->assertEquals('test@example.com', $supplier->contact_email);
        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'Test Company Ltd',
            'contact_email' => 'test@example.com',
        ]);
    }

    public function test_update_an_existing_supplier(): void
    {
        $supplier = Supplier::factory()->create([
            'name' => 'Original Name',
            'status' => 'active',
        ]);

        $updateData = [
            'name' => 'Updated Company Name',
            'status' => 'inactive',
        ];
        $result = $this->repository->updateSupplier($supplier, $updateData);

        $this->assertTrue($result);
        $supplier->refresh();
        $this->assertEquals('Updated Company Name', $supplier->name);
        $this->assertEquals('inactive', $supplier->status);
        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'Updated Company Name',
            'status' => 'inactive',
        ]);
    }

    public function test_update_supplier_with_partial_data(): void
    {
        $supplier = Supplier::factory()->create([
            'name' => 'Original Name',
            'status' => 'active',
            'contact_email' => 'original@example.com',
        ]);

        $updateData = ['name' => 'Partially Updated Name'];
        $result = $this->repository->updateSupplier($supplier, $updateData);

        $this->assertTrue($result);
        $supplier->refresh();
        $this->assertEquals('Partially Updated Name', $supplier->name);
        $this->assertEquals('active', $supplier->status);
        $this->assertEquals('original@example.com', $supplier->contact_email);
    }

    public function test_delete_an_existing_supplier(): void
    {
        $supplier = Supplier::factory()->create();
        $supplierId = $supplier->id;

        $result = $this->repository->deleteSupplier($supplier);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('suppliers', ['id' => $supplierId]);
        $this->assertFalse($supplier->exists);
    }

    /**
     * Test data provider for supplier creation validation
     */
    public static function validSupplierDataProvider(): array
    {
        return [
            'internal supplier' => [
                [
                    'name' => 'Internal Dept',
                    'type' => 'internal',
                    'contact_email' => 'internal@company.com',
                    'contact_phone' => '+1111111111',
                    'status' => 'active',
                ]
            ],
            'external supplier' => [
                [
                    'name' => 'External Partner',
                    'type' => 'external',
                    'contact_email' => 'partner@external.com',
                    'contact_phone' => '+2222222222',
                    'status' => 'inactive',
                ]
            ],
        ];
    }
}
