<?php

namespace Tests\Feature\Commands;

use App\Models\PurchaseOrder;
use App\Models\Voucher;
use App\Services\Voucher\VoucherCipherService;
use App\Services\Voucher\VoucherDeduplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillVoucherCodeHashCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();
        $this->key = base64_decode(config('services.voucher.encryption_key'));
    }

    public function test_backfills_code_hash_for_encrypted_vouchers(): void
    {
        $cipher = app(VoucherCipherService::class);
        $plainCode = 'ABC123';
        $purchaseOrder = PurchaseOrder::factory()->create();

        $voucher = Voucher::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'code' => $cipher->encryptCode($plainCode),
            'code_hash' => null,
        ]);

        $this->artisan('vouchers:backfill-code-hash')->assertSuccessful();

        $this->assertSame(
            hash_hmac('sha256', $plainCode, $this->key),
            $voucher->fresh()->code_hash
        );
    }

    public function test_backfills_code_hash_for_legacy_plain_text_vouchers(): void
    {
        $plainCode = 'LEGACY-CODE';
        $purchaseOrder = PurchaseOrder::factory()->create();

        $voucher = Voucher::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'code' => $plainCode,
            'code_hash' => null,
        ]);

        $this->artisan('vouchers:backfill-code-hash')->assertSuccessful();

        $this->assertSame(
            hash_hmac('sha256', $plainCode, $this->key),
            $voucher->fresh()->code_hash
        );
    }

    public function test_skips_vouchers_that_already_have_code_hash(): void
    {
        $dedup = app(VoucherDeduplicationService::class);
        $existingHash = $dedup->computeHash('ALREADY-HASHED');
        $purchaseOrder = PurchaseOrder::factory()->create();

        $voucher = Voucher::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'code' => 'ALREADY-HASHED',
            'code_hash' => $existingHash,
        ]);

        $this->artisan('vouchers:backfill-code-hash')->assertSuccessful();

        $this->assertSame($existingHash, $voucher->fresh()->code_hash);
    }

    public function test_skips_vouchers_with_null_code(): void
    {
        $purchaseOrder = PurchaseOrder::factory()->create();

        $voucher = Voucher::factory()->processing()->create([
            'purchase_order_id' => $purchaseOrder->id,
        ]);

        $this->artisan('vouchers:backfill-code-hash')->assertSuccessful();

        $this->assertNull($voucher->fresh()->code_hash);
    }

    public function test_command_is_idempotent(): void
    {
        $cipher = app(VoucherCipherService::class);
        $purchaseOrder = PurchaseOrder::factory()->create();

        Voucher::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'code' => $cipher->encryptCode('IDEMPOTENT'),
            'code_hash' => null,
        ]);

        $this->artisan('vouchers:backfill-code-hash')->assertSuccessful();
        $this->artisan('vouchers:backfill-code-hash')->assertSuccessful();

        $this->assertSame(1, Voucher::whereNotNull('code_hash')->count());
    }
}
