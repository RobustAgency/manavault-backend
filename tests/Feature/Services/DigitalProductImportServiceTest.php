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
        $csvContent = "name,sku,brand,description,cost_price,face_value,selling_price,currency,region,tags,metadata\n";
        $csvContent .= 'Gaming Card,SKU-001,Nintendo,Nintendo gift card,50.00,60.00,55.00,usd,US,["gaming", "monster"]'."\n";
        $csvContent .= 'Movie Voucher,SKU-002,Disney,Disney movie voucher,25.00,30.00,28.00,eur,EU,["movies", "boat"]'."\n";

        $tempFile = $this->createTempFile($csvContent, 'csv');

        $uploadedFile = new UploadedFile($tempFile, 'vouchers.csv', 'text/csv', null, true);

        $result = $this->service->importDigitalProducts($uploadedFile, $this->supplier->id);

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

    public function test_import_from_fixture_file(): void
    {
        $fixtureFile = base_path('tests/Fixtures/digital_products.csv');

        $this->assertFileExists($fixtureFile, 'Fixture file does not exist at '.$fixtureFile);

        $content = file_get_contents($fixtureFile);
        $this->assertNotEmpty($content, 'Fixture file is empty');

        $uploadedFile = UploadedFile::fake()->createWithContent('digital_products.csv', $content);

        $result = $this->service->importDigitalProducts($uploadedFile, $this->supplier->id);

        // Verify all 12 products from the fixture were imported
        $this->assertDatabaseCount('digital_products', 12);

        // Verify products from the fixture exist
        $this->assertDatabaseHas('digital_products', [
            'supplier_id' => $this->supplier->id,
            'name' => 'Gaming Card',
            'sku' => 'SKU-GAMING-001',
            'brand' => 'Nintendo',
            'currency' => 'usd',
            'region' => 'US',
        ]);

        $this->assertDatabaseHas('digital_products', [
            'supplier_id' => $this->supplier->id,
            'name' => 'Movie Voucher',
            'sku' => 'SKU-MOVIE-001',
            'brand' => 'Disney',
            'currency' => 'eur',
            'region' => 'EU',
        ]);

        $this->assertDatabaseHas('digital_products', [
            'supplier_id' => $this->supplier->id,
            'name' => 'Netflix Subscription',
            'sku' => 'SKU-NETFLIX-001',
            'brand' => 'Netflix',
            'currency' => 'usd',
        ]);

        $this->assertDatabaseHas('digital_products', [
            'supplier_id' => $this->supplier->id,
            'name' => 'Best Buy Card',
            'sku' => 'SKU-BESTBUY-001',
            'brand' => 'Best Buy',
            'currency' => 'usd',
            'region' => 'US',
        ]);
    }

    public function test_fixture_file_contains_valid_data(): void
    {
        $fixtureFile = base_path('tests/Fixtures/digital_products.csv');

        $this->assertFileExists($fixtureFile);

        $lines = file($fixtureFile, FILE_IGNORE_NEW_LINES);
        $this->assertGreaterThanOrEqual(2, count($lines), 'Fixture should have header + at least 1 data row');

        // Verify header row
        $header = str_getcsv($lines[0]);
        $expectedHeaders = ['name', 'sku', 'brand', 'description', 'cost_price', 'face_value', 'selling_price', 'currency', 'region', 'tags', 'metadata'];
        $this->assertEquals($expectedHeaders, $header, 'CSV headers do not match expected format');

        // Verify data rows have valid structure
        for ($i = 1; $i < count($lines); $i++) {
            $row = str_getcsv($lines[$i]);
            $this->assertCount(count($header), $row, "Row {$i} has incorrect number of columns");

            // Verify required fields are not empty
            $this->assertNotEmpty($row[0], "Row {$i}: name is empty");
            $this->assertNotEmpty($row[1], "Row {$i}: sku is empty");
            $this->assertNotEmpty($row[4], "Row {$i}: cost_price is empty");
            $this->assertNotEmpty($row[5], "Row {$i}: face_value is empty");
            $this->assertNotEmpty($row[6], "Row {$i}: selling_price is empty");
            $this->assertNotEmpty($row[7], "Row {$i}: currency is empty");

            // Verify cost_price is numeric
            $this->assertTrue(is_numeric($row[4]), "Row {$i}: cost_price '{$row[4]}' is not numeric");

            // Verify face_value is numeric
            $this->assertTrue(is_numeric($row[5]), "Row {$i}: face_value '{$row[5]}' is not numeric");

            // Verify selling_price is numeric
            $this->assertTrue(is_numeric($row[6]), "Row {$i}: selling_price '{$row[6]}' is not numeric");

            // Verify currency is valid (usd, eur, gbp, aud, cad, etc.)
            $validCurrencies = ['usd', 'eur', 'gbp', 'aud', 'cad', 'jpy', 'inr', 'mxn'];
            $this->assertContains(
                strtolower($row[7]),
                $validCurrencies,
                "Row {$i}: currency '{$row[7]}' is not in the expected list"
            );
        }
    }

    public function test_fixture_file_has_expected_product_count(): void
    {
        $fixtureFile = base_path('tests/Fixtures/digital_products.csv');

        $lines = file($fixtureFile, FILE_IGNORE_NEW_LINES);
        $productCount = count($lines) - 1; // Subtract header row

        $this->assertGreaterThan(0, $productCount, 'Fixture file should contain at least one product');
        $this->assertLessThanOrEqual(100, $productCount, 'Fixture file should not contain excessive products');

        // The fixture should have exactly 12 products based on its creation
        $this->assertEquals(12, $productCount, 'Fixture file should contain exactly 12 products');
    }

    public function test_fixture_file_products_have_unique_skus(): void
    {
        $fixtureFile = base_path('tests/Fixtures/digital_products.csv');

        $lines = file($fixtureFile, FILE_IGNORE_NEW_LINES);
        $skus = [];

        for ($i = 1; $i < count($lines); $i++) {
            $row = str_getcsv($lines[$i]);
            $sku = $row[1]; // SKU is the second column
            $skus[] = $sku;
        }

        $uniqueSkus = array_unique($skus);
        $this->assertCount(
            count($skus),
            $uniqueSkus,
            'Fixture file contains duplicate SKUs: '.json_encode(array_diff_assoc($skus, $uniqueSkus))
        );
    }
}
