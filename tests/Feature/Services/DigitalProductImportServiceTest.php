<?php

namespace Tests\Feature\Services;

use Tests\TestCase;
use App\Models\Supplier;
use Illuminate\Http\UploadedFile;
use App\Services\DigitalProductImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DigitalProductImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private DigitalProductImportService $service;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DigitalProductImportService::class);
        $this->supplier = Supplier::factory()->create();
    }

    public function test_successfully_import_valid_csv_file(): void
    {
        // Create a valid CSV file
        $csvContent = "name,sku,brand,description,cost_price,currency,region,tags,metadata\n";
        $csvContent .= 'Gaming Card,SKU-001,Nintendo,Nintendo gift card,50.00,usd,US,["gaming", "monster"]'."\n";
        $csvContent .= 'Movie Voucher,SKU-002,Disney,Disney movie voucher,25.00,eur,EU,["movies", "boat"]'."\n";

        $tempFile = $this->createTempFile($csvContent, 'csv');

        $uploadedFile = new UploadedFile($tempFile, 'vouchers.csv', 'text/csv', null, true);

        $result = $this->service->importDigitalProducts($uploadedFile, $this->supplier->id);

        $this->assertTrue($result);
        $this->assertDatabaseHas('digital_products', [
            'supplier_id' => $this->supplier->id,
            'name' => 'Gaming Card',
            'sku' => 'SKU-001',
        ]);
        $this->assertDatabaseHas('digital_products', [
            'supplier_id' => $this->supplier->id,
            'name' => 'Movie Voucher',
            'sku' => 'SKU-002',
        ]);

        $this->cleanupTempFile($tempFile);

    }

    /**
     * Helper method to create a temporary file with specific content and extension
     */
    private function createTempFile(string $content, string $extension): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'voucher_test_').'.'.$extension;
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
