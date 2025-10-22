<?php

namespace Tests\Feature\Repositories;

use App\Models\PurchaseOrder;
use App\Repositories\VoucherRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class VoucherRepositoryTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private VoucherRepository $voucherRepository;
    private PurchaseOrder $purchaseOrder;

    public function setUp(): void
    {
        parent::setUp();
        $this->voucherRepository = app(VoucherRepository::class);
        $this->purchaseOrder = PurchaseOrder::factory()->create();
    }

    public function test_import_vouchers_with_valid_csv(): void
    {
        $csvFile = $this->createValidVoucherCsv([
            'VCH-001',
            'VCH-002',
            'VCH-003',
        ]);

        $data = [
            'filePath' => $csvFile,
            'purchaseOrderID' => $this->purchaseOrder->id,
        ];

        $result = $this->voucherRepository->importVouchers($data);

        $this->assertTrue($result);

        // Verify vouchers were created
        $this->assertDatabaseCount('vouchers', 3);
        $this->assertDatabaseHas('vouchers', [
            'code' => 'VCH-001',
            'purchase_order_id' => $this->purchaseOrder->id,
        ]);
        $this->assertDatabaseHas('vouchers', [
            'code' => 'VCH-002',
            'purchase_order_id' => $this->purchaseOrder->id,
        ]);
        $this->assertDatabaseHas('vouchers', [
            'code' => 'VCH-003',
            'purchase_order_id' => $this->purchaseOrder->id,
        ]);

        $this->cleanupTempFile($csvFile);
    }

    public function test_import_vouchers_with_valid_xlsx(): void
    {
        $xlsxFile = $this->createValidVoucherXlsx([
            'VCH-XLSX-001',
            'VCH-XLSX-002',
        ]);

        $data = [
            'filePath' => $xlsxFile,
            'purchaseOrderID' => $this->purchaseOrder->id,
        ];

        $result = $this->voucherRepository->importVouchers($data);

        $this->assertTrue($result);

        // Verify vouchers were created
        $this->assertDatabaseCount('vouchers', 2);
        $this->assertDatabaseHas('vouchers', [
            'code' => 'VCH-XLSX-001',
            'purchase_order_id' => $this->purchaseOrder->id,
        ]);
        $this->assertDatabaseHas('vouchers', [
            'code' => 'VCH-XLSX-002',
            'purchase_order_id' => $this->purchaseOrder->id,
        ]);

        $this->cleanupTempFile($xlsxFile);
    }

    /**
     * Create a valid CSV file with voucher codes
     */
    private function createValidVoucherCsv(array $voucherCodes): string
    {
        $csvFile = tempnam(sys_get_temp_dir(), 'voucher_test_') . '.csv';

        $handle = fopen($csvFile, 'w');

        // Write header
        fputcsv($handle, ['code']);

        // Write voucher codes
        foreach ($voucherCodes as $code) {
            fputcsv($handle, [$code]);
        }

        fclose($handle);

        return $csvFile;
    }

    private function createValidVoucherXlsx(array $voucherCodes): string
    {
        $xlsxFile = tempnam(sys_get_temp_dir(), 'voucher_test_') . '.xlsx';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Write header
        $sheet->setCellValue('A1', 'code');

        // Write voucher codes
        $row = 2;
        foreach ($voucherCodes as $code) {
            $sheet->setCellValue("A{$row}", $code);
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($xlsxFile);

        return $xlsxFile;
    }

    /**
     * Clean up temporary test files
     */
    private function cleanupTempFile(string $filePath): void
    {
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}
