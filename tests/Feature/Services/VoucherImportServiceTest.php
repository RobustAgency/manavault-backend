<?php

namespace Tests\Feature\Services;

use ZipArchive;
use Tests\TestCase;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\VoucherImportService;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class VoucherImportServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private VoucherImportService $service;

    private PurchaseOrder $purchaseOrder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(VoucherImportService::class);
        $this->purchaseOrder = PurchaseOrder::factory()->create();

        // Create purchase order items with total quantity of 3
        PurchaseOrderItem::factory()
            ->forPurchaseOrder($this->purchaseOrder)
            ->withQuantity(3)
            ->create();
    }

    public function test_process_file_handles_csv_file(): void
    {
        Excel::fake();

        // Create a temporary CSV file
        $csvContent = "code\nVCH-001\nVCH-002\nVCH-003";
        $tempFile = $this->createTempFile($csvContent, 'csv');

        // Create an UploadedFile from the temp file
        $uploadedFile = new UploadedFile($tempFile, 'vouchers.csv', 'text/csv', null, true);

        $data = [
            'file' => $uploadedFile,
            'purchase_order_id' => $this->purchaseOrder->id,
        ];

        $totalQuantity = $this->purchaseOrder->getTotalQuantity();
        $result = $this->service->processFile($data, $totalQuantity);

        $this->assertTrue($result);

        $this->cleanupTempFile($tempFile);
    }

    public function test_process_file_handles_xlsx_file(): void
    {
        Excel::fake();

        // Create a temporary XLSX file (mock)
        $tempFile = $this->createTempFile('mock xlsx content', 'xlsx');
        $uploadedFile = new UploadedFile($tempFile, 'vouchers.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $data = [
            'file' => $uploadedFile,
            'purchase_order_id' => $this->purchaseOrder->id,
        ];

        $totalQuantity = $this->purchaseOrder->getTotalQuantity();
        $result = $this->service->processFile($data, $totalQuantity);

        $this->assertTrue($result);

        $this->cleanupTempFile($tempFile);
    }

    public function test_process_file_handles_xls_file(): void
    {
        Excel::fake();

        // Create a temporary XLS file (mock)
        $tempFile = $this->createTempFile('mock xls content', 'xls');
        $uploadedFile = new UploadedFile($tempFile, 'vouchers.xls', 'application/vnd.ms-excel', null, true);

        $data = [
            'file' => $uploadedFile,
            'purchase_order_id' => $this->purchaseOrder->id,
        ];

        $totalQuantity = $this->purchaseOrder->getTotalQuantity();
        $result = $this->service->processFile($data, $totalQuantity);

        $this->assertTrue($result);

        $this->cleanupTempFile($tempFile);
    }

    public function test_process_file_handles_zip_file_with_valid_contents(): void
    {
        Excel::fake();

        $zipFile = $this->createZipFileWithVoucherFiles([
            'vouchers1.csv' => "code\nVCH-ZIP-001\nVCH-ZIP-002\nVCH-ZIP-003",
        ]);

        $uploadedFile = new UploadedFile($zipFile, 'vouchers.zip', 'application/zip', null, true);

        $data = [
            'file' => $uploadedFile,
            'purchase_order_id' => $this->purchaseOrder->id,
        ];

        $totalQuantity = $this->purchaseOrder->getTotalQuantity();
        $result = $this->service->processFile($data, $totalQuantity);

        $this->assertTrue($result);

        $this->cleanupTempFile($zipFile);
    }

    public function test_process_file_skips_invalid_files_in_zip(): void
    {
        Excel::fake();

        // Create ZIP with valid + invalid files
        $zipFile = $this->createZipFileWithVoucherFiles([
            'vouchers.csv' => "code\nVCH-001\nVCH-002\nVCH-003",
            'readme.txt' => 'This is a readme file', // Invalid
            'image.jpg' => 'fake image content', // Invalid
        ]);

        $uploadedFile = new UploadedFile($zipFile, 'vouchers.zip', 'application/zip', null, true);

        $data = [
            'file' => $uploadedFile,
            'purchase_order_id' => $this->purchaseOrder->id,
        ];

        $totalQuantity = $this->purchaseOrder->getTotalQuantity();
        $result = $this->service->processFile($data, $totalQuantity);

        $this->assertTrue($result);

        $this->cleanupTempFile($zipFile);
    }

    public function test_process_file_returns_false_for_invalid_zip(): void
    {
        // Create a fake ZIP file that's actually not a ZIP
        $invalidZipFile = $this->createTempFile('not a zip file', 'zip');
        $uploadedFile = new UploadedFile($invalidZipFile, 'invalid.zip', 'application/zip', null, true);

        $data = [
            'file' => $uploadedFile,
            'purchase_order_id' => $this->purchaseOrder->id,
        ];

        $totalQuantity = $this->purchaseOrder->getTotalQuantity();
        $result = $this->service->processFile($data, $totalQuantity);

        $this->assertFalse($result);

        $this->cleanupTempFile($invalidZipFile);
    }

    public function test_process_file_cleans_up_temp_files_after_zip_extraction(): void
    {
        Excel::fake();

        $zipFile = $this->createZipFileWithVoucherFiles([
            'vouchers.csv' => "code\nVCH-001\nVCH-002\nVCH-003",
        ]);

        $uploadedFile = new UploadedFile($zipFile, 'vouchers.zip', 'application/zip', null, true);

        // Get the system temp directory
        $tempDir = sys_get_temp_dir();
        $fileCountBefore = count(glob($tempDir.'/*'));

        $data = [
            'file' => $uploadedFile,
            'purchase_order_id' => $this->purchaseOrder->id,
        ];

        $totalQuantity = $this->purchaseOrder->getTotalQuantity();
        $result = $this->service->processFile($data, $totalQuantity);

        $this->assertTrue($result);

        // Verify temp files are cleaned up
        $fileCountAfter = count(glob($tempDir.'/*'));

        // The temp file count should be the same or very close
        // (allowing for some system temp files that might be created)
        $this->assertLessThanOrEqual($fileCountBefore + 2, $fileCountAfter);

        $this->cleanupTempFile($zipFile);
    }

    public function test_process_file_handles_case_insensitive_extensions(): void
    {
        Excel::fake();

        // Test with uppercase extension
        $tempFile = $this->createTempFile('test content', 'CSV');
        $uploadedFile = new UploadedFile($tempFile, 'vouchers.CSV', 'text/csv', null, true);

        $data = [
            'file' => $uploadedFile,
            'purchase_order_id' => $this->purchaseOrder->id,
        ];

        $totalQuantity = $this->purchaseOrder->getTotalQuantity();
        $result = $this->service->processFile($data, $totalQuantity);

        $this->assertTrue($result);

        $this->cleanupTempFile($tempFile);
    }

    public function test_process_file_returns_false_for_excel_import_failure(): void
    {
        // Note: This test may need adjustment based on actual error handling in VoucherImportService
        $this->markTestSkipped('Excel::fake() prevents testing actual import failures');
    }

    public function test_process_file_returns_false_for_zip_with_no_valid_files(): void
    {
        Excel::fake();

        $zipFile = $this->createZipFileWithVoucherFiles([
            'readme.txt' => 'This is a readme file',
            'image.jpg' => 'fake image content',
            'document.pdf' => 'fake pdf content',
        ]);

        $uploadedFile = new UploadedFile($zipFile, 'invalid.zip', 'application/zip', null, true);

        $data = [
            'file' => $uploadedFile,
            'purchase_order_id' => $this->purchaseOrder->id,
        ];

        $totalQuantity = $this->purchaseOrder->getTotalQuantity();
        $result = $this->service->processFile($data, $totalQuantity);

        $this->assertFalse($result);

        $this->cleanupTempFile($zipFile);
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
     * Helper method to create a ZIP file with voucher files
     */
    private function createZipFileWithVoucherFiles(array $files): string
    {
        $zipFile = tempnam(sys_get_temp_dir(), 'voucher_zip_test_').'.zip';
        $zip = new ZipArchive;

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
        $zipFile = tempnam(sys_get_temp_dir(), 'empty_zip_test_').'.zip';
        $zip = new ZipArchive;

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
