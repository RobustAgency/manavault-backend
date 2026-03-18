<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Supplier;
use PHPUnit\Framework\MockObject\MockObject;
use App\Services\Ezcards\EzcardsPlaceOrderService;
use App\Services\Gift2Games\Gift2GamesPlaceOrderService;
use App\Services\PurchaseOrder\PurchaseOrderPlacementService;

class PurchaseOrderPlacementServiceTest extends TestCase
{
    private EzcardsPlaceOrderService&MockObject $ezcardsPlaceOrderService;

    private Gift2GamesPlaceOrderService&MockObject $gift2GamesPlaceOrderService;

    private PurchaseOrderPlacementService $placementService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ezcardsPlaceOrderService = $this->createMock(EzcardsPlaceOrderService::class);
        $this->gift2GamesPlaceOrderService = $this->createMock(Gift2GamesPlaceOrderService::class);

        $this->placementService = new PurchaseOrderPlacementService(
            $this->ezcardsPlaceOrderService,
            $this->gift2GamesPlaceOrderService,
        );
    }

    public function test_routes_to_ezcards_when_supplier_slug_is_ez_cards(): void
    {
        $supplier = new Supplier(['slug' => 'ez_cards', 'type' => 'external']);
        $orderItems = [['digital_product_id' => 1, 'quantity' => 1, 'unit_cost' => 10.0, 'subtotal' => 10.0]];
        $expected = ['transactionId' => 'TXN-001'];

        $this->ezcardsPlaceOrderService
            ->expects($this->once())
            ->method('placeOrder')
            ->with($orderItems, 'PO-TEST-001', 'usd')
            ->willReturn($expected);

        $this->gift2GamesPlaceOrderService->expects($this->never())->method('placeOrder');

        $result = $this->placementService->placeOrder($supplier, $orderItems, 'PO-TEST-001', 'usd');

        $this->assertSame($expected, $result);
    }

    public function test_routes_to_gift2games_when_slug_starts_with_gift2games(): void
    {
        $supplier = new Supplier(['slug' => 'gift2games', 'type' => 'external']);
        $orderItems = [['digital_product_id' => 2, 'quantity' => 1, 'unit_cost' => 5.0, 'subtotal' => 5.0]];
        $expected = ['transactionId' => 'TXN-002'];

        $this->gift2GamesPlaceOrderService
            ->expects($this->once())
            ->method('placeOrder')
            ->with($orderItems, 'PO-TEST-002', 'gift2games')
            ->willReturn($expected);

        $this->ezcardsPlaceOrderService->expects($this->never())->method('placeOrder');

        $result = $this->placementService->placeOrder($supplier, $orderItems, 'PO-TEST-002', 'usd');

        $this->assertSame($expected, $result);
    }

    public function test_routes_to_gift2games_when_slug_starts_with_gift_dash_2_dash_games(): void
    {
        $supplier = new Supplier(['slug' => 'gift-2-games-us', 'type' => 'external']);
        $orderItems = [['digital_product_id' => 3, 'quantity' => 2, 'unit_cost' => 8.0, 'subtotal' => 16.0]];
        $expected = ['transactionId' => 'TXN-003'];

        $this->gift2GamesPlaceOrderService
            ->expects($this->once())
            ->method('placeOrder')
            ->with($orderItems, 'PO-TEST-003', 'gift-2-games-us')
            ->willReturn($expected);

        $result = $this->placementService->placeOrder($supplier, $orderItems, 'PO-TEST-003', 'usd');

        $this->assertSame($expected, $result);
    }

    public function test_throws_runtime_exception_for_unknown_supplier(): void
    {
        $supplier = new Supplier(['slug' => 'unknown_supplier', 'type' => 'external']);

        $this->ezcardsPlaceOrderService->expects($this->never())->method('placeOrder');
        $this->gift2GamesPlaceOrderService->expects($this->never())->method('placeOrder');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown external supplier: unknown_supplier');

        $this->placementService->placeOrder($supplier, [], 'PO-TEST-004', 'usd');
    }
}
