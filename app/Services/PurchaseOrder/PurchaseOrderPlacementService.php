<?php

namespace App\Services\PurchaseOrder;

use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Services\Giftery\GifteryPlaceOrderService;
use App\Services\Tikkery\TikkeryPlaceOrderService;
use App\Services\Irewardify\IrewardifyPlaceOrderService;

class PurchaseOrderPlacementService
{
    public function __construct(
        private IrewardifyPlaceOrderService $irewardifyPlaceOrderService,
        private GifteryPlaceOrderService $gifteryPlaceOrderService,
        private TikkeryPlaceOrderService $tikkeryPlaceOrderService,
    ) {}

    public function placeOrder(Supplier $supplier, array $orderItems, string $orderNumber, string $currency, PurchaseOrder $purchaseOrder): array
    {
        try {
            if ($supplier->slug === 'irewardify') {
                return $this->irewardifyPlaceOrderService->placeOrder($orderItems, $orderNumber);
            }

            if ($supplier->slug === 'giftery-api') {
                return $this->gifteryPlaceOrderService->placeOrder($orderItems, $orderNumber);
            }

            if ($supplier->slug === 'tikkery') {
                return $this->tikkeryPlaceOrderService->placeOrder($orderItems, $orderNumber);
            }

            throw new \RuntimeException("Unknown external supplier: {$supplier->slug}");
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
