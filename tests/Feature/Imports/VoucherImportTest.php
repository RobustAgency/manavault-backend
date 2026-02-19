<?php

namespace Tests\Feature\Imports;

use Tests\TestCase;
use App\Models\Voucher;
use App\Models\PurchaseOrder;
use App\Imports\VoucherImport;
use App\Models\DigitalProduct;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Collection;
use Illuminate\Foundation\Testing\WithFaker;
use App\Services\Voucher\VoucherCipherService;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

class VoucherImportTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private PurchaseOrder $purchaseOrder;

    private VoucherCipherService $voucherCipherService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->purchaseOrder = PurchaseOrder::factory()->create();
        $this->voucherCipherService = app(VoucherCipherService::class);

        // Create purchase order items
        $digitalProduct1 = DigitalProduct::factory()->create();
        $digitalProduct2 = DigitalProduct::factory()->create();

        PurchaseOrderItem::factory()
            ->forPurchaseOrder($this->purchaseOrder)
            ->withQuantity(2)
            ->create(['digital_product_id' => $digitalProduct1->id]);

        PurchaseOrderItem::factory()
            ->forPurchaseOrder($this->purchaseOrder)
            ->withQuantity(1)
            ->create(['digital_product_id' => $digitalProduct2->id]);
    }

    public function test_voucher_import_creates_vouchers_from_collection(): void
    {
        $digitalProduct1 = $this->purchaseOrder->items->first()->digital_product_id;
        $digitalProduct2 = $this->purchaseOrder->items->last()->digital_product_id;

        $import = new VoucherImport($this->purchaseOrder->id);

        $collection = new Collection([
            ['code' => 'VCH-001', 'digital_product_id' => $digitalProduct1],
            ['code' => 'VCH-002', 'digital_product_id' => $digitalProduct1],
            ['code' => 'VCH-003', 'digital_product_id' => $digitalProduct2],
        ]);

        $import->collection($collection);

        // Verify vouchers were created
        $this->assertEquals(3, Voucher::count());

        // Verify vouchers are encrypted and can be decrypted back to original values
        $vouchers = Voucher::where('purchase_order_id', $this->purchaseOrder->id)->get();
        $this->assertCount(3, $vouchers);

        $decryptedCodes = $vouchers->map(function ($voucher) {
            return $this->voucherCipherService->decryptCode($voucher->code);
        })->toArray();

        $this->assertContains('VCH-001', $decryptedCodes);
        $this->assertContains('VCH-002', $decryptedCodes);
        $this->assertContains('VCH-003', $decryptedCodes);
    }

    public function test_voucher_import_throws_exception_when_quantity_mismatches(): void
    {
        $this->expectException(ValidationException::class);

        $digitalProduct1 = $this->purchaseOrder->items->first()->digital_product_id;

        $import = new VoucherImport($this->purchaseOrder->id);

        // Only provide 1 voucher when purchase order expects 2
        $collection = new Collection([
            ['code' => 'VCH-001', 'digital_product_id' => $digitalProduct1],
        ]);

        $import->collection($collection);
    }

    public function test_voucher_import_throws_exception_when_digital_product_missing(): void
    {
        $this->expectException(ValidationException::class);

        $import = new VoucherImport($this->purchaseOrder->id);

        // Provide a digital product that doesn't exist in purchase order
        $collection = new Collection([
            ['code' => 'VCH-001', 'digital_product_id' => 9999],
        ]);

        $import->collection($collection);
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

    public function test_voucher_import_with_multiple_digital_products(): void
    {
        $digitalProduct1 = $this->purchaseOrder->items->first()->digital_product_id;
        $digitalProduct2 = $this->purchaseOrder->items->last()->digital_product_id;

        $import = new VoucherImport($this->purchaseOrder->id);

        $collection = new Collection([
            ['code' => 'VCH-001', 'digital_product_id' => $digitalProduct1],
            ['code' => 'VCH-002', 'digital_product_id' => $digitalProduct1],
            ['code' => 'VCH-003', 'digital_product_id' => $digitalProduct2],
        ]);

        $import->collection($collection);

        // Verify all vouchers were created
        $this->assertEquals(3, Voucher::where('purchase_order_id', $this->purchaseOrder->id)->count());

        // Verify vouchers are encrypted
        $vouchers = Voucher::where('purchase_order_id', $this->purchaseOrder->id)->get();
        foreach ($vouchers as $voucher) {
            $this->assertTrue($this->voucherCipherService->isEncrypted($voucher->code));
        }
    }

    public function test_voucher_import_encrypts_codes(): void
    {
        $digitalProduct1 = $this->purchaseOrder->items->first()->digital_product_id;
        $digitalProduct2 = $this->purchaseOrder->items->last()->digital_product_id;

        $import = new VoucherImport($this->purchaseOrder->id);

        $plainCodes = ['PLAINTEXT-001', 'PLAINTEXT-002', 'PLAINTEXT-003'];
        $collection = new Collection([
            ['code' => $plainCodes[0], 'digital_product_id' => $digitalProduct1],
            ['code' => $plainCodes[1], 'digital_product_id' => $digitalProduct1],
            ['code' => $plainCodes[2], 'digital_product_id' => $digitalProduct2],
        ]);

        $import->collection($collection);

        $vouchers = Voucher::where('purchase_order_id', $this->purchaseOrder->id)->get();

        foreach ($vouchers as $voucher) {
            // Codes should be encrypted in database
            $this->assertTrue($this->voucherCipherService->isEncrypted($voucher->code));

            // Decrypted codes should match original plain codes
            $decryptedCode = $this->voucherCipherService->decryptCode($voucher->code);
            $this->assertContains($decryptedCode, $plainCodes);
        }
    }
}
