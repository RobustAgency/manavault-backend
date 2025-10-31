<?php

namespace Tests\Feature\Imports;

use App\Imports\VoucherImport;
use App\Models\PurchaseOrder;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class VoucherImportTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private PurchaseOrder $purchaseOrder;

    public function setUp(): void
    {
        parent::setUp();
        $this->purchaseOrder = PurchaseOrder::factory()->create();
    }

    public function test_voucher_import_creates_vouchers_from_collection(): void
    {
        $import = new VoucherImport($this->purchaseOrder->id);

        $collection = new Collection([
            ['code' => 'VCH-001'],
            ['code' => 'VCH-002'],
            ['code' => 'VCH-003'],
        ]);

        $import->collection($collection);

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

        $this->assertEquals(3, Voucher::count());
    }

    public function test_voucher_import_skips_empty_code_rows(): void
    {
        $import = new VoucherImport($this->purchaseOrder->id);

        $collection = new Collection([
            ['code' => 'VCH-001'],
            ['code' => ''], // Empty code
            ['code' => null], // Null code
            ['code' => 'VCH-002'],
        ]);

        $import->collection($collection);

        $this->assertEquals(2, Voucher::count());

        $this->assertDatabaseHas('vouchers', ['code' => 'VCH-001']);
        $this->assertDatabaseHas('vouchers', ['code' => 'VCH-002']);
    }

    public function test_voucher_import_handles_different_column_names(): void
    {
        $import = new VoucherImport($this->purchaseOrder->id);

        // Test with different possible column headers
        $collection = new Collection([
            ['code' => 'VCH-001'],
            ['voucher_code' => 'VCH-002'], // Alternative column name
            ['Code' => 'VCH-003'], // Case variation
        ]);

        $import->collection($collection);

        // Should create vouchers for valid 'code' entries
        $this->assertDatabaseHas('vouchers', ['code' => 'VCH-001']);

        // Note: The current implementation only looks for 'code' column
        // If you want to support alternative column names, update the import logic
        $this->assertEquals(1, Voucher::count());
    }

    public function test_voucher_import_associates_with_correct_purchase_order(): void
    {
        $purchaseOrder1 = PurchaseOrder::factory()->create();
        $purchaseOrder2 = PurchaseOrder::factory()->create();

        $import1 = new VoucherImport($purchaseOrder1->id);
        $import2 = new VoucherImport($purchaseOrder2->id);

        $collection1 = new Collection([['code' => 'PO1-VCH-001']]);
        $collection2 = new Collection([['code' => 'PO2-VCH-001']]);

        $import1->collection($collection1);
        $import2->collection($collection2);

        $this->assertDatabaseHas('vouchers', [
            'code' => 'PO1-VCH-001',
            'purchase_order_id' => $purchaseOrder1->id,
        ]);

        $this->assertDatabaseHas('vouchers', [
            'code' => 'PO2-VCH-001',
            'purchase_order_id' => $purchaseOrder2->id,
        ]);
    }

    public function test_voucher_import_batch_size_configuration(): void
    {
        $import = new VoucherImport($this->purchaseOrder->id);

        $this->assertEquals(100, $import->batchSize());
    }

    public function test_voucher_import_chunk_size_configuration(): void
    {
        $import = new VoucherImport($this->purchaseOrder->id);

        $this->assertEquals(100, $import->chunkSize());
    }

    public function test_voucher_import_handles_large_datasets(): void
    {
        $import = new VoucherImport($this->purchaseOrder->id);

        // Create a large collection to test batch processing
        $largeCollection = new Collection();
        for ($i = 1; $i <= 250; $i++) {
            $largeCollection->push(['code' => sprintf('BULK-VCH-%03d', $i)]);
        }

        $import->collection($largeCollection);

        $this->assertEquals(250, Voucher::count());

        // Verify some random entries
        $this->assertDatabaseHas('vouchers', [
            'code' => 'BULK-VCH-001',
            'purchase_order_id' => $this->purchaseOrder->id,
        ]);

        $this->assertDatabaseHas('vouchers', [
            'code' => 'BULK-VCH-250',
            'purchase_order_id' => $this->purchaseOrder->id,
        ]);
    }

    public function test_voucher_import_with_excel_file(): void
    {
        // Create a temporary CSV content for testing
        $csvContent = "code\nVCH-CSV-001\nVCH-CSV-002\nVCH-CSV-003";
        $tempFile = tempnam(sys_get_temp_dir(), 'voucher_test') . '.csv';
        file_put_contents($tempFile, $csvContent);

        try {
            Excel::import(new VoucherImport($this->purchaseOrder->id), $tempFile);

            $this->assertEquals(3, Voucher::count());

            $this->assertDatabaseHas('vouchers', [
                'code' => 'VCH-CSV-001',
                'purchase_order_id' => $this->purchaseOrder->id,
            ]);

            $this->assertDatabaseHas('vouchers', [
                'code' => 'VCH-CSV-002',
                'purchase_order_id' => $this->purchaseOrder->id,
            ]);

            $this->assertDatabaseHas('vouchers', [
                'code' => 'VCH-CSV-003',
                'purchase_order_id' => $this->purchaseOrder->id,
            ]);
        } finally {
            // Clean up temp file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function test_voucher_import_with_empty_file(): void
    {
        $import = new VoucherImport($this->purchaseOrder->id);

        $emptyCollection = new Collection([]);

        $import->collection($emptyCollection);

        $this->assertEquals(0, Voucher::count());
    }

    public function test_voucher_import_with_malformed_data(): void
    {
        $import = new VoucherImport($this->purchaseOrder->id);

        $collection = new Collection([
            ['code' => 'VALID-001'],
            ['invalid_column' => 'some_value'], // No 'code' column
            ['code' => 'VALID-002'],
            [], // Empty row
        ]);

        $import->collection($collection);

        $this->assertEquals(2, Voucher::count());
        $this->assertDatabaseHas('vouchers', ['code' => 'VALID-001']);
        $this->assertDatabaseHas('vouchers', ['code' => 'VALID-002']);
    }
}
