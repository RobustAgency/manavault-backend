<?php

namespace Tests\Feature\Imports;

use Tests\TestCase;
use App\Models\Voucher;
use App\Models\PurchaseOrder;
use App\Imports\VoucherImport;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class VoucherImportTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private PurchaseOrder $purchaseOrder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->purchaseOrder = PurchaseOrder::factory()->create();

        // Create purchase order items with total quantity of 3
        PurchaseOrderItem::factory()
            ->forPurchaseOrder($this->purchaseOrder)
            ->withQuantity(2)
            ->create();

        PurchaseOrderItem::factory()
            ->forPurchaseOrder($this->purchaseOrder)
            ->withQuantity(1)
            ->create();
    }

    public function test_voucher_import_creates_vouchers_from_collection(): void
    {
        $totalQuantity = $this->purchaseOrder->getTotalQuantity();
        $import = new VoucherImport($this->purchaseOrder->id, $totalQuantity);

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

    public function test_voucher_import_throws_exception_when_row_count_mismatches(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The number of voucher codes (2) does not match the total quantity of the purchase order (3).');

        $totalQuantity = $this->purchaseOrder->getTotalQuantity();
        $import = new VoucherImport($this->purchaseOrder->id, $totalQuantity);

        $collection = new Collection([
            ['code' => 'VCH-001'],
            ['code' => 'VCH-002'],
            // Missing one voucher code - should throw exception
        ]);

        $import->collection($collection);
    }

    public function test_voucher_import_throws_exception_for_invalid_voucher_code(): void
    {
        $this->expectException(\RuntimeException::class);

        $totalQuantity = $this->purchaseOrder->getTotalQuantity();
        $import = new VoucherImport($this->purchaseOrder->id, $totalQuantity);

        $collection = new Collection([
            ['code' => 'VCH-001'],
            ['code' => ''], // Empty code - should fail validation
            ['code' => 'VCH-003'],
        ]);

        $import->collection($collection);
    }

    public function test_voucher_import_associates_with_correct_purchase_order(): void
    {
        $purchaseOrder1 = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder1)->withQuantity(1)->create();

        $purchaseOrder2 = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder2)->withQuantity(1)->create();

        $import1 = new VoucherImport($purchaseOrder1->id, $purchaseOrder1->getTotalQuantity());
        $import2 = new VoucherImport($purchaseOrder2->id, $purchaseOrder2->getTotalQuantity());

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
        $totalQuantity = $this->purchaseOrder->getTotalQuantity();
        $import = new VoucherImport($this->purchaseOrder->id, $totalQuantity);

        $this->assertEquals(100, $import->batchSize());
    }

    public function test_voucher_import_chunk_size_configuration(): void
    {
        $totalQuantity = $this->purchaseOrder->getTotalQuantity();
        $import = new VoucherImport($this->purchaseOrder->id, $totalQuantity);

        $this->assertEquals(100, $import->chunkSize());
    }

    public function test_voucher_import_handles_large_datasets(): void
    {
        // Create a purchase order with 250 items
        $purchaseOrder = PurchaseOrder::factory()->create();
        PurchaseOrderItem::factory()->forPurchaseOrder($purchaseOrder)->withQuantity(250)->create();

        $import = new VoucherImport($purchaseOrder->id, $purchaseOrder->getTotalQuantity());

        // Create a large collection to test batch processing
        $largeCollection = new Collection;
        for ($i = 1; $i <= 250; $i++) {
            $largeCollection->push(['code' => sprintf('BULK-VCH-%03d', $i)]);
        }

        $import->collection($largeCollection);

        $this->assertEquals(250, Voucher::where('purchase_order_id', $purchaseOrder->id)->count());

        // Verify some random entries
        $this->assertDatabaseHas('vouchers', [
            'code' => 'BULK-VCH-001',
            'purchase_order_id' => $purchaseOrder->id,
        ]);

        $this->assertDatabaseHas('vouchers', [
            'code' => 'BULK-VCH-250',
            'purchase_order_id' => $purchaseOrder->id,
        ]);
    }

    public function test_voucher_import_with_excel_file(): void
    {
        // Create a temporary CSV content for testing
        $csvContent = "code\nVCH-CSV-001\nVCH-CSV-002\nVCH-CSV-003";
        $tempFile = tempnam(sys_get_temp_dir(), 'voucher_test').'.csv';
        file_put_contents($tempFile, $csvContent);

        try {
            $totalQuantity = $this->purchaseOrder->getTotalQuantity();
            Excel::import(new VoucherImport($this->purchaseOrder->id, $totalQuantity), $tempFile);

            $this->assertEquals(3, Voucher::where('purchase_order_id', $this->purchaseOrder->id)->count());

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

    public function test_voucher_import_throws_exception_with_empty_file(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The number of voucher codes (0) does not match the total quantity of the purchase order (3).');

        $totalQuantity = $this->purchaseOrder->getTotalQuantity();
        $import = new VoucherImport($this->purchaseOrder->id, $totalQuantity);

        $emptyCollection = new Collection([]);

        $import->collection($emptyCollection);
    }

    public function test_voucher_import_throws_exception_with_malformed_data(): void
    {
        $this->expectException(\RuntimeException::class);

        $totalQuantity = $this->purchaseOrder->getTotalQuantity();
        $import = new VoucherImport($this->purchaseOrder->id, $totalQuantity);

        $collection = new Collection([
            ['code' => 'VALID-001'],
            ['invalid_column' => 'some_value'], // No 'code' column - will fail validation
            ['code' => 'VALID-002'],
        ]);

        $import->collection($collection);
    }
}
