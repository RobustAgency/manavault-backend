<?php

namespace Tests\Feature\Services;

use App\Models\DigitalProduct;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Voucher;
use App\Services\Voucher\VoucherCreateService;
use App\Services\Voucher\VoucherDeduplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherDeduplicationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private VoucherDeduplicationService $dedup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dedup = app(VoucherDeduplicationService::class);
    }

    public function test_importing_same_code_twice_for_same_product_creates_only_one_voucher(): void
    {
        $supplier = Supplier::factory()->create();
        $product = DigitalProduct::factory()->forSupplier($supplier)->create();
        $purchaseOrder = PurchaseOrder::factory()->create();

        PurchaseOrderItem::factory()
            ->forPurchaseOrder($purchaseOrder)
            ->forDigitalProduct($product)
            ->withQuantity(2)
            ->create(['supplier_id' => $supplier->id]);

        $service = app(VoucherCreateService::class);

        $service->createVouchers([
            'purchase_order_id' => $purchaseOrder->id,
            'voucher_codes' => [
                ['digital_product_id' => $product->id, 'code' => 'SAME-CODE'],
                ['digital_product_id' => $product->id, 'code' => 'SAME-CODE'],
            ],
        ]);

        $this->assertSame(1, Voucher::where('purchase_order_id', $purchaseOrder->id)->count());
    }

    public function test_same_code_for_different_products_creates_two_vouchers(): void
    {
        $supplier = Supplier::factory()->create();
        $productApple = DigitalProduct::factory()->forSupplier($supplier)->create();
        $productSpotify = DigitalProduct::factory()->forSupplier($supplier)->create();
        $purchaseOrder = PurchaseOrder::factory()->create();

        PurchaseOrderItem::factory()
            ->forPurchaseOrder($purchaseOrder)
            ->forDigitalProduct($productApple)
            ->withQuantity(1)
            ->create(['supplier_id' => $supplier->id]);

        PurchaseOrderItem::factory()
            ->forPurchaseOrder($purchaseOrder)
            ->forDigitalProduct($productSpotify)
            ->withQuantity(1)
            ->create(['supplier_id' => $supplier->id]);

        $service = app(VoucherCreateService::class);

        $service->createVouchers([
            'purchase_order_id' => $purchaseOrder->id,
            'voucher_codes' => [
                ['digital_product_id' => $productApple->id, 'code' => 'SHARED-CODE'],
                ['digital_product_id' => $productSpotify->id, 'code' => 'SHARED-CODE'],
            ],
        ]);

        $this->assertSame(2, Voucher::where('purchase_order_id', $purchaseOrder->id)->count());
    }

    public function test_digital_product_id_and_code_hash_are_persisted_on_new_voucher(): void
    {
        $supplier = Supplier::factory()->create();
        $product = DigitalProduct::factory()->forSupplier($supplier)->create();
        $purchaseOrder = PurchaseOrder::factory()->create();

        PurchaseOrderItem::factory()
            ->forPurchaseOrder($purchaseOrder)
            ->forDigitalProduct($product)
            ->withQuantity(1)
            ->create(['supplier_id' => $supplier->id]);

        $service = app(VoucherCreateService::class);

        $service->createVouchers([
            'purchase_order_id' => $purchaseOrder->id,
            'voucher_codes' => [
                ['digital_product_id' => $product->id, 'code' => 'MY-CODE'],
            ],
        ]);

        $voucher = Voucher::where('purchase_order_id', $purchaseOrder->id)->first();

        $this->assertSame($product->id, $voucher->digital_product_id);
        $this->assertSame($this->dedup->computeHash('MY-CODE'), $voucher->code_hash);
    }
}
