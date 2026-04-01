<?php

namespace Tests\Feature\Controllers\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Brand;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductExportToPdfTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'super_admin']);
    }

    public function test_export_products_to_pdf(): void
    {
        $this->actingAs($this->admin);

        $brand = Brand::factory()->create();
        Product::factory()->count(5)->create(['brand_id' => $brand->id]);

        $response = $this->getJson('/api/products/export/pdf');

        $response->assertStatus(200);
        $this->assertTrue($response->headers->has('Content-Disposition'));
    }

    public function test_export_products_to_pdf_with_filters(): void
    {
        $this->actingAs($this->admin);

        $brand = Brand::factory()->create();
        Product::factory()->count(5)->create([
            'brand_id' => $brand->id,
            'status' => 'active',
            'currency' => 'usd',
        ]);
        Product::factory()->count(3)->create([
            'brand_id' => $brand->id,
            'status' => 'inactive',
            'currency' => 'usd',
        ]);

        $response = $this->getJson('/api/products/export/pdf?status=active');

        $response->assertStatus(200);
        $this->assertTrue($response->headers->has('Content-Disposition'));
    }

    public function test_export_products_to_pdf_with_brand_filter(): void
    {
        $this->actingAs($this->admin);

        $brand1 = Brand::factory()->create();
        $brand2 = Brand::factory()->create();
        Product::factory()->count(5)->create(['brand_id' => $brand1->id]);
        Product::factory()->count(3)->create(['brand_id' => $brand2->id]);

        $response = $this->getJson("/api/products/export/pdf?brand_id={$brand1->id}");

        $response->assertStatus(200);
        $this->assertTrue($response->headers->has('Content-Disposition'));
    }

    public function test_export_empty_products_to_pdf(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/products/export/pdf');

        $response->assertStatus(200);
        $this->assertTrue($response->headers->has('Content-Disposition'));
    }
}
