<?php

namespace Tests\Feature\Services;

use App\Models\DigitalProduct;
use App\Models\PurchaseOrder;
use App\Models\Voucher;
use App\Services\Voucher\VoucherDeduplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherDeduplicationServiceTest extends TestCase
{
    use RefreshDatabase;

    private VoucherDeduplicationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(VoucherDeduplicationService::class);
    }

    public function test_compute_hash_is_deterministic(): void
    {
        $this->assertSame(
            $this->service->computeHash('ABC123'),
            $this->service->computeHash('ABC123')
        );
    }

    public function test_compute_hash_produces_64_char_hex_string(): void
    {
        $this->assertSame(64, strlen($this->service->computeHash('ABC123')));
    }

    public function test_compute_hash_differs_for_different_codes(): void
    {
        $this->assertNotSame(
            $this->service->computeHash('ABC123'),
            $this->service->computeHash('XYZ789')
        );
    }

    public function test_is_duplicate_returns_false_when_no_match_exists(): void
    {
        $product = DigitalProduct::factory()->create();

        $this->assertFalse($this->service->isDuplicate($product->id, 'ABC123'));
    }

    public function test_is_duplicate_returns_true_when_same_product_and_code_exists(): void
    {
        $product = DigitalProduct::factory()->create();
        $purchaseOrder = PurchaseOrder::factory()->create();

        Voucher::factory()->withDigitalProduct($product)->create([
            'purchase_order_id' => $purchaseOrder->id,
            'code' => 'ABC123',
            'code_hash' => $this->service->computeHash('ABC123'),
        ]);

        $this->assertTrue($this->service->isDuplicate($product->id, 'ABC123'));
    }

    public function test_same_code_for_different_product_is_not_duplicate(): void
    {
        $productA = DigitalProduct::factory()->create();
        $productB = DigitalProduct::factory()->create();
        $purchaseOrder = PurchaseOrder::factory()->create();

        Voucher::factory()->withDigitalProduct($productA)->create([
            'purchase_order_id' => $purchaseOrder->id,
            'code' => 'ABC123',
            'code_hash' => $this->service->computeHash('ABC123'),
        ]);

        $this->assertFalse($this->service->isDuplicate($productB->id, 'ABC123'));
    }

    public function test_is_duplicate_returns_false_when_digital_product_id_is_null(): void
    {
        $this->assertFalse($this->service->isDuplicate(null, 'ABC123'));
    }
}
