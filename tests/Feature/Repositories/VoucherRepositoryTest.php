<?php

namespace Tests\Feature\Repositories;

use Tests\TestCase;
use App\Models\Voucher;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Repositories\VoucherRepository;
use Illuminate\Foundation\Testing\WithFaker;
use App\Services\Voucher\VoucherCipherService;
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

    public function test_get_filtered_vouchers_by_purchase_order_id(): void
    {
        // Create vouchers for this purchase order
        Voucher::factory(3)->create([
            'purchase_order_id' => $this->purchaseOrder->id,
        ]);

        // Create vouchers for a different purchase order
        $otherPurchaseOrder = PurchaseOrder::factory()->create();
        Voucher::factory(2)->create([
            'purchase_order_id' => $otherPurchaseOrder->id,
        ]);

        // Filter by purchase order ID
        $result = $this->voucherRepository->getFilteredVouchers([
            'purchase_order_id' => $this->purchaseOrder->id,
        ]);

        $this->assertCount(3, $result->items());
        $this->assertEquals($this->purchaseOrder->id, $result->first()->purchase_order_id);
    }

    public function test_get_filtered_vouchers_with_pagination(): void
    {
        // Create more than 10 vouchers
        Voucher::factory(15)->create([
            'purchase_order_id' => $this->purchaseOrder->id,
        ]);

        // Get with custom per_page
        $result = $this->voucherRepository->getFilteredVouchers([
            'per_page' => 5,
        ]);

        $this->assertCount(5, $result->items());
        $this->assertEquals(15, $result->total());
        $this->assertTrue($result->hasMorePages());
    }

    public function test_decrypt_voucher_code_with_encrypted_voucher(): void
    {
        $plainCode = 'ENCRYPTED-CODE-123';
        $encryptedCode = $this->voucherCipherService->encryptCode($plainCode);

        $voucher = Voucher::factory()->create([
            'code' => $encryptedCode,
            'purchase_order_id' => $this->purchaseOrder->id,
        ]);

        $decryptedCode = $this->voucherRepository->decryptVoucherCode($voucher);

        $this->assertEquals($plainCode, $decryptedCode);
    }

    public function test_decrypt_voucher_code_with_legacy_plain_text_voucher(): void
    {
        $plainCode = 'LEGACY-PLAIN-CODE-456';

        $voucher = Voucher::factory()->create([
            'code' => $plainCode,
            'purchase_order_id' => $this->purchaseOrder->id,
        ]);

        $result = $this->voucherRepository->decryptVoucherCode($voucher);

        $this->assertEquals($plainCode, $result);
        $this->assertFalse($this->voucherCipherService->isEncrypted($voucher->code));
    }

    public function test_decrypt_voucher_code_handles_both_encrypted_and_plain_text(): void
    {
        $encryptedPlainCode = 'NEW-ENCRYPTED-789';
        $encryptedVoucher = Voucher::factory()->create([
            'code' => $this->voucherCipherService->encryptCode($encryptedPlainCode),
            'purchase_order_id' => $this->purchaseOrder->id,
        ]);

        $legacyPlainCode = 'OLD-PLAIN-TEXT-999';
        $legacyVoucher = Voucher::factory()->create([
            'code' => $legacyPlainCode,
            'purchase_order_id' => $this->purchaseOrder->id,
        ]);

        $this->assertEquals($encryptedPlainCode, $this->voucherRepository->decryptVoucherCode($encryptedVoucher));
        $this->assertEquals($legacyPlainCode, $this->voucherRepository->decryptVoucherCode($legacyVoucher));
    }
}
