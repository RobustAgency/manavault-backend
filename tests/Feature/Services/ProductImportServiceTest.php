<?php

namespace Tests\Feature\Services;

use Tests\TestCase;
use App\Models\Brand;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use App\Services\ProductImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductImportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProductImportService::class);
    }

    public function test_successfully_import_valid_csv_file(): void
    {
        // Create a valid CSV file
        $csvContent = "name,sku,brand_name,description,short_description,long_description,face_value,currency,status,tags,regions\n";
        $csvContent .= 'Gaming Card,SKU-001,Nike,A gaming card product,Short desc,Long description,50.00,usd,active,"[""gaming"", ""card""]","[""US"", ""EU""]"'."\n";
        $csvContent .= 'Movie Voucher,SKU-002,Adidas,A movie voucher,Short desc,Long description,25.00,eur,active,"[""movies""]","[""EU""]"'."\n";

        $tempFile = $this->createTempFile($csvContent, 'csv');
        $uploadedFile = UploadedFile::fake()->createWithContent('products.csv', $csvContent);

        $this->service->importProducts($uploadedFile);

        $this->assertDatabaseHas('products', [
            'name' => 'Gaming Card',
            'sku' => 'SKU-001',
            'face_value' => 50.00,
            'currency' => 'usd',
        ]);

        $this->assertDatabaseHas('products', [
            'name' => 'Movie Voucher',
            'sku' => 'SKU-002',
            'face_value' => 25.00,
            'currency' => 'eur',
        ]);

        // Verify brands were created
        $this->assertDatabaseHas('brands', ['name' => 'Nike']);
        $this->assertDatabaseHas('brands', ['name' => 'Adidas']);

        $this->cleanupTempFile($tempFile);
    }

    public function test_import_with_optional_fields(): void
    {
        $csvContent = "name,sku,brand_name,description,short_description,long_description,face_value,currency,status,tags,regions\n";
        $csvContent .= 'Simple Product,SKU-003,,Just a simple product,,,100.00,usd,active,,'."\n";

        $tempFile = $this->createTempFile($csvContent, 'csv');
        $uploadedFile = UploadedFile::fake()->createWithContent('products.csv', $csvContent);

        $this->service->importProducts($uploadedFile);

        $this->assertDatabaseHas('products', [
            'name' => 'Simple Product',
            'sku' => 'SKU-003',
            'brand_id' => null,
            'description' => 'Just a simple product',
        ]);

        $this->cleanupTempFile($tempFile);
    }

    public function test_import_with_brand_name_resolution(): void
    {
        $brand = Brand::factory()->create(['name' => 'Nike']);

        $csvContent = "name,sku,brand_name,description,short_description,long_description,face_value,currency,status,tags,regions\n";
        $csvContent .= "Nike Gift Card,SKU-004,Nike,Nike brand card,Short,Long,75.00,usd,active,,\n";

        $tempFile = $this->createTempFile($csvContent, 'csv');
        $uploadedFile = UploadedFile::fake()->createWithContent('products.csv', $csvContent);

        $this->service->importProducts($uploadedFile);

        $this->assertDatabaseHas('products', [
            'name' => 'Nike Gift Card',
            'sku' => 'SKU-004',
            'brand_id' => $brand->id,
        ]);

        $this->cleanupTempFile($tempFile);
    }

    public function test_import_fails_with_invalid_currency(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $csvContent = "name,sku,brand_name,description,short_description,long_description,face_value,currency,status,tags,regions\n";
        $csvContent .= 'Bad Product,SKU-005,,Description,Short,Long,50.00,invalid_currency,active,,'."\n";

        $tempFile = $this->createTempFile($csvContent, 'csv');
        $uploadedFile = UploadedFile::fake()->createWithContent('products.csv', $csvContent);

        $this->service->importProducts($uploadedFile);

        $this->cleanupTempFile($tempFile);
    }

    public function test_import_fails_with_invalid_status(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $csvContent = "name,sku,brand_name,description,short_description,long_description,face_value,currency,status,tags,regions\n";
        $csvContent .= 'Bad Product,SKU-006,,Description,Short,Long,50.00,usd,invalid_status,,'."\n";

        $tempFile = $this->createTempFile($csvContent, 'csv');
        $uploadedFile = UploadedFile::fake()->createWithContent('products.csv', $csvContent);

        $this->service->importProducts($uploadedFile);

        $this->cleanupTempFile($tempFile);
    }

    public function test_import_fails_with_missing_required_fields(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $csvContent = "name,sku,brand_name,description,short_description,long_description,face_value,currency,status,tags,regions\n";
        $csvContent .= 'Incomplete Product,SKU-007,,Description,Short,Long,,usd,active,,'."\n";

        $tempFile = $this->createTempFile($csvContent, 'csv');
        $uploadedFile = UploadedFile::fake()->createWithContent('products.csv', $csvContent);

        $this->service->importProducts($uploadedFile);

        $this->cleanupTempFile($tempFile);
    }

    public function test_import_fails_with_duplicate_sku_in_file(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $csvContent = "name,sku,brand_name,description,short_description,long_description,face_value,currency,status,tags,regions\n";
        $csvContent .= 'Product One,SKU-008,,Description,Short,Long,50.00,usd,active,,'."\n";
        $csvContent .= 'Product Two,SKU-008,,Description,Short,Long,60.00,eur,active,,'."\n";

        $tempFile = $this->createTempFile($csvContent, 'csv');
        $uploadedFile = UploadedFile::fake()->createWithContent('products.csv', $csvContent);

        $this->service->importProducts($uploadedFile);

        $this->cleanupTempFile($tempFile);
    }

    public function test_import_fails_with_duplicate_sku_in_database(): void
    {
        Product::factory()->create(['sku' => 'SKU-009']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $csvContent = "name,sku,brand_name,description,short_description,long_description,face_value,currency,status,tags,regions\n";
        $csvContent .= 'New Product,SKU-009,,Description,Short,Long,50.00,usd,active,,'."\n";

        $tempFile = $this->createTempFile($csvContent, 'csv');
        $uploadedFile = UploadedFile::fake()->createWithContent('products.csv', $csvContent);

        $this->service->importProducts($uploadedFile);

        $this->cleanupTempFile($tempFile);
    }

    public function test_import_with_negative_face_value_fails(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $csvContent = "name,sku,brand_name,description,short_description,long_description,face_value,currency,status,tags,regions\n";
        $csvContent .= 'Bad Product,SKU-010,,Description,Short,Long,-50.00,usd,active,,'."\n";

        $tempFile = $this->createTempFile($csvContent, 'csv');
        $uploadedFile = UploadedFile::fake()->createWithContent('products.csv', $csvContent);

        $this->service->importProducts($uploadedFile);

        $this->cleanupTempFile($tempFile);
    }

    /**
     * Helper method to create a temporary file with specific content and extension
     */
    private function createTempFile(string $content, string $extension): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'product_test_').'.'.$extension;
        file_put_contents($tempFile, $content);

        return $tempFile;
    }

    /**
     * Helper method to clean up temporary files
     */
    private function cleanupTempFile(string $filePath): void
    {
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}
