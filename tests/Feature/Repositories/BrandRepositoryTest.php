<?php

namespace Tests\Feature\Repositories;

use Tests\TestCase;
use App\Models\Brand;
use Illuminate\Http\UploadedFile;
use App\Repositories\BrandRepository;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BrandRepositoryTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private BrandRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(BrandRepository::class);
    }

    public function test_get_filtered_brands_returns_paginated_results(): void
    {
        Brand::factory()->count(20)->create();

        $brands = $this->repository->getFilteredBrands();

        $this->assertCount(15, $brands->items());
        $this->assertEquals(20, $brands->total());
    }

    public function test_get_filtered_brands_with_custom_per_page(): void
    {
        Brand::factory()->count(25)->create();

        $brands = $this->repository->getFilteredBrands(['per_page' => 10]);

        $this->assertCount(10, $brands->items());
        $this->assertEquals(25, $brands->total());
    }

    public function test_get_filtered_brands_by_name(): void
    {
        Brand::factory()->create(['name' => 'Nike']);
        Brand::factory()->create(['name' => 'Adidas']);
        Brand::factory()->create(['name' => 'Puma']);

        $brands = $this->repository->getFilteredBrands(['name' => 'Nike']);

        $this->assertCount(1, $brands->items());
        $this->assertEquals('Nike', $brands->items()[0]->name);
    }

    public function test_get_filtered_brands_by_partial_name(): void
    {
        Brand::factory()->create(['name' => 'Nike Air']);
        Brand::factory()->create(['name' => 'Nike Pro']);
        Brand::factory()->create(['name' => 'Adidas']);

        $brands = $this->repository->getFilteredBrands(['name' => 'Nike']);

        $this->assertCount(2, $brands->items());
    }

    public function test_get_filtered_brands_returns_alphabetically_sorted(): void
    {
        Brand::factory()->create(['name' => 'Zebra Brand']);
        Brand::factory()->create(['name' => 'Alpha Brand']);
        Brand::factory()->create(['name' => 'Beta Brand']);

        $brands = $this->repository->getFilteredBrands();

        $this->assertEquals('Alpha Brand', $brands->items()[0]->name);
        $this->assertEquals('Beta Brand', $brands->items()[1]->name);
        $this->assertEquals('Zebra Brand', $brands->items()[2]->name);
    }

    public function test_create_brand(): void
    {
        $data = ['name' => 'New Brand'];

        $brand = $this->repository->createBrand($data);

        $this->assertInstanceOf(Brand::class, $brand);
        $this->assertEquals('New Brand', $brand->name);
        $this->assertDatabaseHas('brands', ['name' => 'New Brand']);
    }

    public function test_update_brand(): void
    {
        $brand = Brand::factory()->create(['name' => 'Old Name']);

        $updatedBrand = $this->repository->updateBrand($brand, ['name' => 'New Name']);

        $this->assertEquals('New Name', $updatedBrand->name);
        $this->assertDatabaseHas('brands', ['id' => $brand->id, 'name' => 'New Name']);
        $this->assertDatabaseMissing('brands', ['name' => 'Old Name']);
    }

    public function test_delete_brand(): void
    {
        $brand = Brand::factory()->create(['name' => 'Brand to Delete']);

        $result = $this->repository->deleteBrand($brand);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('brands', ['id' => $brand->id]);
    }

    public function test_get_filtered_brands_returns_empty_when_no_match(): void
    {
        Brand::factory()->create(['name' => 'Nike']);
        Brand::factory()->create(['name' => 'Adidas']);

        $brands = $this->repository->getFilteredBrands(['name' => 'Puma']);

        $this->assertCount(0, $brands->items());
    }

    public function test_create_brand_with_image(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('brand-logo.jpg');

        $data = [
            'name' => 'Brand with Image',
            'image' => $file,
        ];

        $brand = $this->repository->createBrand($data);

        $this->assertInstanceOf(Brand::class, $brand);
        $this->assertEquals('Brand with Image', $brand->name);
        $this->assertNotNull($brand->image);
        $this->assertDatabaseHas('brands', ['name' => 'Brand with Image']);

        $this->assertTrue(Storage::disk('public')->exists($brand->image));
    }

    public function test_update_brand_with_new_image(): void
    {
        Storage::fake('public');

        $oldFile = UploadedFile::fake()->image('old-logo.jpg');
        $brand = $this->repository->createBrand([
            'name' => 'Brand',
            'image' => $oldFile,
        ]);

        $oldImagePath = $brand->image;

        $newFile = UploadedFile::fake()->image('new-logo.jpg');
        $updatedBrand = $this->repository->updateBrand($brand, [
            'name' => 'Updated Brand',
            'image' => $newFile,
        ]);

        $this->assertEquals('Updated Brand', $updatedBrand->name);
        $this->assertNotEquals($oldImagePath, $updatedBrand->image);
        $this->assertTrue(Storage::disk('public')->exists($updatedBrand->image));
        $this->assertFalse(Storage::disk('public')->exists($oldImagePath));
    }

    public function test_update_brand_without_changing_image(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('logo.jpg');
        $brand = $this->repository->createBrand([
            'name' => 'Brand',
            'image' => $file,
        ]);

        $originalImage = $brand->image;

        $updatedBrand = $this->repository->updateBrand($brand, [
            'name' => 'Updated Name',
        ]);

        $this->assertEquals('Updated Name', $updatedBrand->name);
        $this->assertEquals($originalImage, $updatedBrand->image);
        $this->assertTrue(Storage::disk('public')->exists($originalImage));
    }

    public function test_delete_brand_removes_associated_image(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('logo.jpg');
        $brand = $this->repository->createBrand([
            'name' => 'Brand with Image',
            'image' => $file,
        ]);

        $imagePath = $brand->image;
        $this->assertTrue(Storage::disk('public')->exists($imagePath));

        $result = $this->repository->deleteBrand($brand);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('brands', ['id' => $brand->id]);
        $this->assertFalse(Storage::disk('public')->exists($imagePath));
    }

    public function test_delete_brand_without_image(): void
    {
        $brand = Brand::factory()->create(['name' => 'Brand without Image']);

        $result = $this->repository->deleteBrand($brand);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('brands', ['id' => $brand->id]);
    }

    public function test_update_brand_returns_fresh_instance(): void
    {
        $brand = Brand::factory()->create(['name' => 'Original Name']);

        $updatedBrand = $this->repository->updateBrand($brand, ['name' => 'New Name']);

        $this->assertNotSame($brand, $updatedBrand);
        $this->assertEquals('New Name', $updatedBrand->name);

        // Verify the original instance is not automatically updated
        $brand->refresh();
        $this->assertEquals('New Name', $brand->name);
    }
}
