<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\DigitalProduct;
use App\Models\PurchaseOrderItem;
use App\Actions\Giftery\PlaceOrderAction;
use App\Services\Giftery\GifteryPlaceOrderService;

class GifteryPlaceOrderServiceTest extends TestCase
{
    public function test_place_order_all_codes_available_immediately(): void
    {
        $mockAction = $this->mock(PlaceOrderAction::class);

        $item = $this->createMockPurchaseOrderItem(1, 5000);

        $confirmResponse = [
            'uuid' => 'uuid-001',
            'status' => 'confirmed',
            'codes' => [
                [
                    'code' => 'CODE-001',
                    'pin' => '1111',
                    'serial' => 'SN-001',
                    'expiryDate' => '2027-12-31',
                ],
            ],
        ];

        $mockAction
            ->shouldReceive('execute')
            ->times(5)
            ->andReturn($confirmResponse);

        $service = new GifteryPlaceOrderService($mockAction);
        $result = $service->placeOrder([$item], 'PO-001');

        $this->assertCount(5, $result['vouchers']);
        $this->assertEmpty($result['pending']);
        $this->assertEquals('CODE-001', $result['vouchers'][0]['code']);
    }

    public function test_place_order_all_codes_pending(): void
    {
        $mockAction = $this->mock(PlaceOrderAction::class);

        $item = $this->createMockPurchaseOrderItem(1, 2);

        $confirmResponse = [
            'uuid' => 'uuid-pending-001',
            'status' => 'confirmed',
            'codes' => [],
        ];

        $mockAction
            ->shouldReceive('execute')
            ->times(2)
            ->andReturn($confirmResponse);

        $service = new GifteryPlaceOrderService($mockAction);
        $result = $service->placeOrder([$item], 'PO-002');

        $this->assertEmpty($result['vouchers']);
        $this->assertCount(2, $result['pending']);
        $this->assertEquals('uuid-pending-001', $result['pending'][0]['transactionUUID']);
    }

    public function test_place_order_mixed_immediate_and_pending(): void
    {
        $mockAction = $this->mock(PlaceOrderAction::class);

        $item = $this->createMockPurchaseOrderItem(1, 3);

        $confirmWithCodes = [
            'uuid' => 'uuid-with-codes',
            'status' => 'confirmed',
            'codes' => [
                ['code' => 'CODE-READY', 'pin' => '2222', 'serial' => 'SN-READY', 'expiryDate' => '2027-12-31'],
            ],
        ];

        $confirmWithoutCodes = [
            'uuid' => 'uuid-pending',
            'status' => 'confirmed',
            'codes' => [],
        ];

        $mockAction
            ->shouldReceive('execute')
            ->times(3)
            ->andReturnValues([$confirmWithCodes, $confirmWithoutCodes, $confirmWithCodes]);

        $service = new GifteryPlaceOrderService($mockAction);
        $result = $service->placeOrder([$item], 'PO-003');

        $this->assertCount(2, $result['vouchers']);
        $this->assertCount(1, $result['pending']);
    }

    public function test_place_order_continues_on_individual_failure(): void
    {
        $mockAction = $this->mock(PlaceOrderAction::class);

        $item = $this->createMockPurchaseOrderItem(1, 2);

        $confirmResponse = [
            'uuid' => 'uuid-success',
            'status' => 'confirmed',
            'codes' => [
                ['code' => 'SUCCESS-CODE', 'pin' => '3333', 'serial' => 'SN-SUCCESS', 'expiryDate' => '2027-12-31'],
            ],
        ];

        $mockAction
            ->shouldReceive('execute')
            ->times(2)
            ->andReturnValues([
                new \Exception('Item failed'),
                $confirmResponse,
            ]);

        $service = new GifteryPlaceOrderService($mockAction);
        $result = $service->placeOrder([$item], 'PO-004');

        $this->assertCount(1, $result['vouchers']);
        $this->assertEmpty($result['pending']);
    }

    public function test_place_order_empty_items(): void
    {
        $mockAction = $this->mock(PlaceOrderAction::class);

        $service = new GifteryPlaceOrderService($mockAction);
        $result = $service->placeOrder([], 'PO-EMPTY');

        $this->assertEmpty($result['vouchers']);
        $this->assertEmpty($result['pending']);
    }

    private function createMockPurchaseOrderItem(int $itemId, int $quantity)
    {
        $digitalProduct = \Mockery::mock(DigitalProduct::class);
        $digitalProduct->sku = 5000 + $itemId;

        $item = \Mockery::mock(PurchaseOrderItem::class);
        $item->id = $itemId;
        $item->quantity = $quantity;
        $item->digital_product_id = $itemId;
        $item->digitalProduct = $digitalProduct;

        return $item;
    }
}
