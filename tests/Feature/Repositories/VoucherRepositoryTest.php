<?php

namespace Tests\Feature\Repositories;

use Tests\TestCase;
use App\Models\Voucher;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\UploadedFile;
use App\Services\VoucherCipherService;
use App\Repositories\VoucherRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class VoucherRepositoryTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private VoucherRepository $voucherRepository;

    private VoucherCipherService $voucherCipherService;

    private PurchaseOrder $purchaseOrder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->voucherRepository = app(VoucherRepository::class);
        $this->voucherCipherService = app(VoucherCipherService::class);
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

        // Verify vouchers are encrypted in database
        $vouchers = Voucher::where('purchase_order_id', $this->purchaseOrder->id)->get();
        foreach ($vouchers as $voucher) {
            $this->assertTrue($this->voucherCipherService->isEncrypted($voucher->code));
        }

        // Verify we can decrypt them back to original values
        $this->assertEquals('VCH-001', $this->voucherRepository->decryptVoucherCode($vouchers[0]));
        $this->assertEquals('VCH-002', $this->voucherRepository->decryptVoucherCode($vouchers[1]));
        $this->assertEquals('VCH-003', $this->voucherRepository->decryptVoucherCode($vouchers[2]));

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

        // Verify vouchers are encrypted in database
        $vouchers = Voucher::where('purchase_order_id', $purchaseOrder->id)->get();
        foreach ($vouchers as $voucher) {
            $this->assertTrue($this->voucherCipherService->isEncrypted($voucher->code));
        }

        // Verify we can decrypt them back to original values
        $this->assertEquals('VCH-XLSX-001', $this->voucherRepository->decryptVoucherCode($vouchers[0]));
        $this->assertEquals('VCH-XLSX-002', $this->voucherRepository->decryptVoucherCode($vouchers[1]));

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

        // Verify vouchers are encrypted in database
        $vouchers = Voucher::where('purchase_order_id', $this->purchaseOrder->id)->get();
        foreach ($vouchers as $voucher) {
            $this->assertTrue($this->voucherCipherService->isEncrypted($voucher->code));
        }

        // Verify we can decrypt them back to original values
        $this->assertEquals('CODE-001', $this->voucherRepository->decryptVoucherCode($vouchers[0]));
        $this->assertEquals('CODE-002', $this->voucherRepository->decryptVoucherCode($vouchers[1]));
        $this->assertEquals('CODE-003', $this->voucherRepository->decryptVoucherCode($vouchers[2]));
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

    public function test_decrypt_voucher_code_with_encrypted_voucher(): void
    {
        // Create an encrypted voucher
        $plainCode = 'ENCRYPTED-CODE-123';
        $encryptedCode = $this->voucherCipherService->encryptCode($plainCode);

        $voucher = Voucher::factory()->create([
            'code' => $encryptedCode,
            'purchase_order_id' => $this->purchaseOrder->id,
        ]);

        // Decrypt and verify
        $decryptedCode = $this->voucherRepository->decryptVoucherCode($voucher);

        $this->assertEquals($plainCode, $decryptedCode);
    }

    public function test_decrypt_voucher_code_with_legacy_plain_text_voucher(): void
    {
        // Create a legacy voucher (plain text, not encrypted)
        $plainCode = 'LEGACY-PLAIN-CODE-456';

        $voucher = Voucher::factory()->create([
            'code' => $plainCode,
            'purchase_order_id' => $this->purchaseOrder->id,
        ]);

        // Should return the plain text as-is
        $result = $this->voucherRepository->decryptVoucherCode($voucher);

        $this->assertEquals($plainCode, $result);
        $this->assertFalse($this->voucherCipherService->isEncrypted($voucher->code));
    }

    public function test_decrypt_voucher_code_handles_both_encrypted_and_plain_text(): void
    {
        // Create one encrypted voucher
        $encryptedPlainCode = 'NEW-ENCRYPTED-789';
        $encryptedVoucher = Voucher::factory()->create([
            'code' => $this->voucherCipherService->encryptCode($encryptedPlainCode),
            'purchase_order_id' => $this->purchaseOrder->id,
        ]);

        // Create one legacy plain text voucher
        $legacyPlainCode = 'OLD-PLAIN-TEXT-999';
        $legacyVoucher = Voucher::factory()->create([
            'code' => $legacyPlainCode,
            'purchase_order_id' => $this->purchaseOrder->id,
        ]);

        // Verify both can be decrypted/retrieved correctly
        $this->assertEquals($encryptedPlainCode, $this->voucherRepository->decryptVoucherCode($encryptedVoucher));
        $this->assertEquals($legacyPlainCode, $this->voucherRepository->decryptVoucherCode($legacyVoucher));
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
