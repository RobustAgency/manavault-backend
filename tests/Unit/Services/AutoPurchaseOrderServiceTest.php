<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Supplier;
use App\Enums\SupplierType;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use App\Services\PurchaseOrderService;
use App\Services\AutoPurchaseOrderService;
use App\Services\VoucherAllocationService;
use PHPUnit\Framework\MockObject\MockObject;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AutoPurchaseOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseOrderService&MockObject $purchaseOrderServiceMock;

    private VoucherAllocationService&MockObject $voucherAllocationServiceMock;

    private AutoPurchaseOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->purchaseOrderServiceMock = $this->createMock(PurchaseOrderService::class);
        $this->voucherAllocationServiceMock = $this->createMock(VoucherAllocationService::class);

        $this->service = new AutoPurchaseOrderService(
            $this->voucherAllocationServiceMock,
            $this->purchaseOrderServiceMock,
        );
    }

    /**
     * Shortfall with a Gift2Games (external) supplier → PO dispatched, returns true.
     */
    public function test_handles_shortfall_with_external_gift2games_supplier(): void
    {
        $supplier = Supplier::factory()->create([
            'slug' => 'gift2games',
            'type' => SupplierType::EXTERNAL->value,
        ]);

        $digitalProduct = DigitalProduct::factory()->forSupplier($supplier)->create([
            'cost_price' => 10.00,
        ]);

        $product = Product::factory()->create(['fulfillment_mode' => 'price']);
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $this->purchaseOrderServiceMock
            ->expects($this->once())
            ->method('createPurchaseOrderForDigitalProduct')
            ->with(
                $this->callback(fn ($dp) => $dp->id === $digitalProduct->id),
                5
            )
            ->willReturn($this->createMock(PurchaseOrder::class));

        $result = $this->service->handleShortfall($product, 5);

        $this->assertTrue($result);
    }

    /**
     * Shortfall with an Ezcards (external) supplier → PO dispatched, returns true.
     */
    public function test_handles_shortfall_with_ezcards_supplier(): void
    {
        $supplier = Supplier::factory()->create([
            'slug' => 'ez_cards',
            'type' => SupplierType::EXTERNAL->value,
        ]);

        $digitalProduct = DigitalProduct::factory()->forSupplier($supplier)->create([
            'cost_price' => 10.00,
        ]);

        $product = Product::factory()->create(['fulfillment_mode' => 'price']);
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $this->purchaseOrderServiceMock
            ->expects($this->once())
            ->method('createPurchaseOrderForDigitalProduct')
            ->with(
                $this->callback(fn ($dp) => $dp->id === $digitalProduct->id),
                3
            )
            ->willReturn($this->createMock(PurchaseOrder::class));

        $result = $this->service->handleShortfall($product, 3);

        $this->assertTrue($result);
    }

    /**
     * Shortfall with only an internal supplier → no PO dispatched, returns false.
     */
    public function test_returns_false_when_only_internal_supplier_exists(): void
    {
        $supplier = Supplier::factory()->create([
            'type' => SupplierType::INTERNAL->value,
        ]);

        $digitalProduct = DigitalProduct::factory()->forSupplier($supplier)->create([
            'cost_price' => 10.00,
        ]);

        $product = Product::factory()->create(['fulfillment_mode' => 'price']);
        $product->digitalProducts()->attach($digitalProduct->id, ['priority' => 1]);

        $this->purchaseOrderServiceMock
            ->expects($this->never())
            ->method('createPurchaseOrderForDigitalProduct');

        $result = $this->service->handleShortfall($product, 4);

        $this->assertFalse($result);
    }

    /**
     * Mixed internal + external → PO dispatched only for the external digital product.
     */
    public function test_dispatches_po_only_for_external_supplier_in_mixed_scenario(): void
    {
        $internalSupplier = Supplier::factory()->create([
            'type' => SupplierType::INTERNAL->value,
        ]);
        $externalSupplier = Supplier::factory()->create([
            'slug' => 'gift2games',
            'type' => SupplierType::EXTERNAL->value,
        ]);

        // Internal dp has lower cost price → evaluated first in PRICE mode but skipped
        $internalDp = DigitalProduct::factory()->forSupplier($internalSupplier)->create([
            'cost_price' => 5.00,
        ]);
        $externalDp = DigitalProduct::factory()->forSupplier($externalSupplier)->create([
            'cost_price' => 10.00,
        ]);

        $product = Product::factory()->create(['fulfillment_mode' => 'price']);
        $product->digitalProducts()->attach($internalDp->id, ['priority' => 1]);
        $product->digitalProducts()->attach($externalDp->id, ['priority' => 2]);

        // Called exactly once — for the external dp, not the internal one
        $this->purchaseOrderServiceMock
            ->expects($this->once())
            ->method('createPurchaseOrderForDigitalProduct')
            ->with(
                $this->callback(fn ($dp) => $dp->id === $externalDp->id),
                2
            )
            ->willReturn($this->createMock(PurchaseOrder::class));

        $result = $this->service->handleShortfall($product, 2);

        $this->assertTrue($result);
    }

    /**
     * Product has no digital products → returns false, no PO dispatched.
     */
    public function test_returns_false_when_no_digital_products(): void
    {
        $product = Product::factory()->create(['fulfillment_mode' => 'price']);

        $this->purchaseOrderServiceMock
            ->expects($this->never())
            ->method('createPurchaseOrderForDigitalProduct');

        $result = $this->service->handleShortfall($product, 2);

        $this->assertFalse($result);
    }
}
