<?php

namespace Tests\Feature\Repositories;

use Tests\TestCase;
use App\Models\Voucher;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\UploadedFile;
use App\Repositories\VoucherRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class VoucherRepositoryTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private VoucherRepository $voucherRepository;

    private PurchaseOrder $purchaseOrder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->voucherRepository = app(VoucherRepository::class);
        $this->purchaseOrder = PurchaseOrder::factory()->create();

        // Create purchase order items with total quantity of 3
        PurchaseOrderItem::factory()
            ->forPurchaseOrder($this->purchaseOrder)
            ->withQuantity(3)
            ->create();
    }

    public function test_import_vouchers_with_valid_csv(): void
    {
        $csvFile = $this->createValidVoucherCsv([
            'VCH-001',
            'VCH-002',
            'VCH-003',
        ]);

        // Create UploadedFile from the temp file
        $uploadedFile = new UploadedFile($csvFile, 'vouchers.csv', 'text/csv', null, true);

        $data = [
            'file' => $uploadedFile,
            'purchase_order_id' => $this->purchaseOrder->id,
        ];

        $result = $this->voucherRepository->storeVouchers($data);

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
        // Create a purchase order with 2 items for this specific test
        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()
            ->forPurchaseOrder($purchaseOrder)
            ->withQuantity(2)
            ->create();

        $xlsxFile = $this->createValidVoucherXlsx([
            'VCH-XLSX-001',
            'VCH-XLSX-002',
        ]);

        // Create UploadedFile from the temp file
        $uploadedFile = new UploadedFile($xlsxFile, 'vouchers.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $data = [
            'file' => $uploadedFile,
            'purchase_order_id' => $purchaseOrder->id,
        ];

        $result = $this->voucherRepository->storeVouchers($data);

        $this->assertTrue($result);

        // Verify vouchers were created
        $this->assertEquals(2, Voucher::where('purchase_order_id', $purchaseOrder->id)->count());
        $this->assertDatabaseHas('vouchers', [
            'code' => 'VCH-XLSX-001',
            'purchase_order_id' => $purchaseOrder->id,
        ]);
        $this->assertDatabaseHas('vouchers', [
            'code' => 'VCH-XLSX-002',
            'purchase_order_id' => $purchaseOrder->id,
        ]);

        $this->cleanupTempFile($xlsxFile);
    }

    public function test_store_vouchers_with_voucher_codes_array(): void
    {
        $data = [
            'voucher_codes' => [
                'CODE-001',
                'CODE-002',
                'CODE-003',
            ],
            'purchase_order_id' => $this->purchaseOrder->id,
        ];

        $result = $this->voucherRepository->storeVouchers($data);

        $this->assertTrue($result);

        // Verify vouchers were created
        $this->assertDatabaseCount('vouchers', 3);
        $this->assertDatabaseHas('vouchers', [
            'code' => 'CODE-001',
            'purchase_order_id' => $this->purchaseOrder->id,
            'status' => 'available',
        ]);
        $this->assertDatabaseHas('vouchers', [
            'code' => 'CODE-002',
            'purchase_order_id' => $this->purchaseOrder->id,
            'status' => 'available',
        ]);
        $this->assertDatabaseHas('vouchers', [
            'code' => 'CODE-003',
            'purchase_order_id' => $this->purchaseOrder->id,
            'status' => 'available',
        ]);
    }

    public function test_store_vouchers_throws_exception_when_voucher_codes_count_mismatches(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The number of voucher codes does not match the total quantity of the purchase order.');

        $data = [
            'voucher_codes' => [
                'CODE-001',
                'CODE-002',
                // Missing one code - purchase order has quantity of 3
            ],
            'purchase_order_id' => $this->purchaseOrder->id,
        ];

        $this->voucherRepository->storeVouchers($data);
    }

    /**
     * Create a valid CSV file with voucher codes
     */
    private function createValidVoucherCsv(array $voucherCodes): string
    {
        $csvFile = tempnam(sys_get_temp_dir(), 'voucher_test_').'.csv';

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
        $xlsxFile = tempnam(sys_get_temp_dir(), 'voucher_test_').'.xlsx';

        $spreadsheet = new Spreadsheet;
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
