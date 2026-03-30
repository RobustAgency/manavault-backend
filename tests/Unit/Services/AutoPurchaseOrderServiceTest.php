<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Supplier;
use App\Enums\SupplierType;
use App\Models\PurchaseOrder;
use App\Models\DigitalProduct;
use App\Services\PurchaseOrderService;
use App\Services\AutoPurchaseOrderService;
use PHPUnit\Framework\MockObject\MockObject;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AutoPurchaseOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseOrderService&MockObject $purchaseOrderServiceMock;

    private AutoPurchaseOrderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->purchaseOrderServiceMock = $this->createMock(PurchaseOrderService::class);

        $this->service = new AutoPurchaseOrderService(
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

        $this->purchaseOrderServiceMock
            ->expects($this->once())
            ->method('createPurchaseOrderForDigitalProduct')
            ->with(
                $this->callback(fn ($dp) => $dp->id === $digitalProduct->id),
                5
            )
            ->willReturn($this->createMock(PurchaseOrder::class));

        $result = $this->service->handleShortfall($digitalProduct, 5);

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

        $this->purchaseOrderServiceMock
            ->expects($this->once())
            ->method('createPurchaseOrderForDigitalProduct')
            ->with(
                $this->callback(fn ($dp) => $dp->id === $digitalProduct->id),
                3
            )
            ->willReturn($this->createMock(PurchaseOrder::class));

        $result = $this->service->handleShortfall($digitalProduct, 3);

        $this->assertTrue($result);
    }

    /**
     * Shortfall with an internal supplier → PO is still dispatched, returns true.
     */
    public function test_handles_shortfall_with_internal_supplier(): void
    {
        $supplier = Supplier::factory()->create([
            'slug' => 'internal',
            'type' => SupplierType::INTERNAL->value,
        ]);

        $digitalProduct = DigitalProduct::factory()->forSupplier($supplier)->create([
            'cost_price' => 10.00,
        ]);

        $this->purchaseOrderServiceMock
            ->expects($this->once())
            ->method('createPurchaseOrderForDigitalProduct')
            ->with(
                $this->callback(fn ($dp) => $dp->id === $digitalProduct->id),
                4
            )
            ->willReturn($this->createMock(PurchaseOrder::class));

        $result = $this->service->handleShortfall($digitalProduct, 4);

        $this->assertTrue($result);
    }
}
