<?php

namespace Tests\Feature\Services;

use App\Imports\VoucherImport;
use App\Models\PurchaseOrder;
use App\Services\VoucherImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Maatwebsite\Excel\Facades\Excel;
use ZipArchive;
use Tests\TestCase;

class VoucherImportServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private VoucherImportService $service;
    private PurchaseOrder $purchaseOrder;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = new VoucherImportService();
        $this->purchaseOrder = PurchaseOrder::factory()->create();
    }

    public function test_process_file_handles_csv_file(): void
    {
        Excel::fake();

        // Create a temporary CSV file
        $csvContent = "code\nVCH-001\nVCH-002\nVCH-003";
        $tempFile = $this->createTempFile($csvContent, 'csv');

        $data = [
            'filePath' => $tempFile,
            'purchaseOrderID' => $this->purchaseOrder->id,
        ];

        $result = $this->service->processFile($data);

        $this->assertTrue($result);

        // Assert that Excel::import was called with correct parameters
        Excel::assertImported($tempFile, function (VoucherImport $import) {
            return true; // Could add more specific assertions here
        });

        $this->cleanupTempFile($tempFile);
    }

    public function test_process_file_handles_xlsx_file(): void
    {
        Excel::fake();

        // Create a temporary XLSX file (mock)
        $tempFile = $this->createTempFile('mock xlsx content', 'xlsx');

        $data = [
            'filePath' => $tempFile,
            'purchaseOrderID' => $this->purchaseOrder->id,
        ];

        $result = $this->service->processFile($data);

        $this->assertTrue($result);

        // Assert that Excel::import was called
        Excel::assertImported($tempFile);

        $this->cleanupTempFile($tempFile);
    }

    public function test_process_file_handles_xls_file(): void
    {
        Excel::fake();

        // Create a temporary XLS file (mock)
        $tempFile = $this->createTempFile('mock xls content', 'xls');

        $data = [
            'filePath' => $tempFile,
            'purchaseOrderID' => $this->purchaseOrder->id,
        ];

        $result = $this->service->processFile($data);

        $this->assertTrue($result);

        // Assert that Excel::import was called
        Excel::assertImported($tempFile);

        $this->cleanupTempFile($tempFile);
    }

    public function test_process_file_handles_zip_file_with_valid_contents(): void
    {
        Excel::fake();

        $zipFile = $this->createZipFileWithVoucherFiles([
            'vouchers1.csv' => "code\nVCH-ZIP-001\nVCH-ZIP-002",
            'vouchers2.xlsx' => "mock xlsx content",
        ]);

        $data = [
            'filePath' => $zipFile,
            'purchaseOrderID' => $this->purchaseOrder->id,
        ];

        $result = $this->service->processFile($data);

        $this->assertTrue($result);

        $tempDir = sys_get_temp_dir();

        Excel::assertImported($tempDir . '/vouchers1.csv');
        Excel::assertImported($tempDir . '/vouchers2.xlsx');

        $this->cleanupTempFile($zipFile);
    }


    public function test_process_file_skips_invalid_files_in_zip(): void
    {
        Excel::fake();

        // Create ZIP with valid + invalid files
        $zipFile = $this->createZipFileWithVoucherFiles([
            'vouchers.csv' => "code\nVCH-001",
            'readme.txt' => "This is a readme file", // Invalid
            'image.jpg' => "fake image content", // Invalid
            'data.xlsx' => "mock xlsx content", // Valid
        ]);

        $data = [
            'filePath' => $zipFile,
            'purchaseOrderID' => $this->purchaseOrder->id,
        ];

        $result = $this->service->processFile($data);

        $this->assertTrue($result);

        $tempDir = sys_get_temp_dir();

        Excel::assertImported($tempDir . '/vouchers.csv');
        Excel::assertImported($tempDir . '/data.xlsx');

        $this->cleanupTempFile($zipFile);
    }


    public function test_process_file_returns_false_for_invalid_zip(): void
    {
        // Create a fake ZIP file that's actually not a ZIP
        $invalidZipFile = $this->createTempFile('not a zip file', 'zip');

        $data = [
            'filePath' => $invalidZipFile,
            'purchaseOrderID' => $this->purchaseOrder->id,
        ];

        $result = $this->service->processFile($data);

        $this->assertFalse($result);

        $this->cleanupTempFile($invalidZipFile);
    }

    public function test_process_file_cleans_up_temp_files_after_zip_extraction(): void
    {
        Excel::fake();

        $zipFile = $this->createZipFileWithVoucherFiles([
            'vouchers.csv' => "code\nVCH-001",
        ]);

        // Get the system temp directory
        $tempDir = sys_get_temp_dir();
        $fileCountBefore = count(glob($tempDir . '/*'));

        $data = [
            'filePath' => $zipFile,
            'purchaseOrderID' => $this->purchaseOrder->id,
        ];

        $result = $this->service->processFile($data);

        $this->assertTrue($result);

        // Verify temp files are cleaned up
        $fileCountAfter = count(glob($tempDir . '/*'));

        // The temp file count should be the same or very close
        // (allowing for some system temp files that might be created)
        $this->assertLessThanOrEqual($fileCountBefore + 1, $fileCountAfter);

        $this->cleanupTempFile($zipFile);
    }

    public function test_process_file_handles_case_insensitive_extensions(): void
    {
        Excel::fake();

        // Test with uppercase extension
        $tempFile = $this->createTempFile('test content', 'CSV');

        $data = [
            'filePath' => $tempFile,
            'purchaseOrderID' => $this->purchaseOrder->id,
        ];

        $result = $this->service->processFile($data);

        $this->assertTrue($result);

        Excel::assertImported($tempFile);

        $this->cleanupTempFile($tempFile);
    }

    public function test_process_file_returns_false_for_excel_import_failure(): void
    {
        Excel::fake();
        Excel::shouldReceive('import')
            ->once()
            ->andThrow(new \Exception('Import failed'));

        $tempFile = $this->createTempFile('test content', 'csv');

        $data = [
            'filePath' => $tempFile,
            'purchaseOrderID' => $this->purchaseOrder->id,
        ];

        $result = $this->service->processFile($data);

        $this->assertFalse($result);

        $this->cleanupTempFile($tempFile);
    }

    public function test_process_file_returns_false_for_empty_zip(): void
    {
        $emptyZipFile = $this->createEmptyZipFile();

        $data = [
            'filePath' => $emptyZipFile,
            'purchaseOrderID' => $this->purchaseOrder->id,
        ];

        $result = $this->service->processFile($data);

        $this->assertFalse($result);

        $this->cleanupTempFile($emptyZipFile);
    }

    public function test_process_file_returns_false_for_zip_with_no_valid_files(): void
    {
        Excel::fake();

        $zipFile = $this->createZipFileWithVoucherFiles([
            'readme.txt' => "This is a readme file",
            'image.jpg' => "fake image content",
            'document.pdf' => "fake pdf content",
        ]);

        $data = [
            'filePath' => $zipFile,
            'purchaseOrderID' => $this->purchaseOrder->id,
        ];

        $result = $this->service->processFile($data);

        $this->assertFalse($result);

        $this->cleanupTempFile($zipFile);
    }

    /**
     * Helper method to create a temporary file with specific content and extension
     */
    private function createTempFile(string $content, string $extension): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'voucher_test_') . '.' . $extension;
        file_put_contents($tempFile, $content);
        return $tempFile;
    }

    /**
     * Helper method to create a ZIP file with voucher files
     */
    private function createZipFileWithVoucherFiles(array $files): string
    {
        $zipFile = tempnam(sys_get_temp_dir(), 'voucher_zip_test_') . '.zip';
        $zip = new ZipArchive();

        if ($zip->open($zipFile, ZipArchive::CREATE) === true) {
            foreach ($files as $filename => $content) {
                $zip->addFromString($filename, $content);
            }
            $zip->close();
        }

        return $zipFile;
    }

    /**
     * Helper method to create an empty ZIP file
     */
    private function createEmptyZipFile(): string
    {
        $zipFile = tempnam(sys_get_temp_dir(), 'empty_zip_test_') . '.zip';
        $zip = new ZipArchive();

        if ($zip->open($zipFile, ZipArchive::CREATE) === true) {
            $zip->close();
        }

        return $zipFile;
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
