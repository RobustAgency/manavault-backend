<?php

namespace App\Services\PurchaseOrder;

use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Services\Ezcards\EzcardsPlaceOrderService;
use App\Services\Giftery\GifteryPlaceOrderService;
use App\Services\Gift2Games\Gift2GamesPlaceOrderService;

class PurchaseOrderPlacementService
{
    public function __construct(
        private EzcardsPlaceOrderService $ezcardsPlaceOrderService,
        private Gift2GamesPlaceOrderService $gift2GamesPlaceOrderService,
        private GifteryPlaceOrderService $gifteryPlaceOrderService,
    ) {}

    public function placeOrder(Supplier $supplier, array $orderItems, string $orderNumber, string $currency, PurchaseOrder $purchaseOrder): array
    {
        try {
            if ($supplier->slug === 'ez_cards') {
                return $this->ezcardsPlaceOrderService->placeOrder($orderItems, $orderNumber, $currency);
            }

            if ($this->isGift2GamesSupplier($supplier)) {
                $this->gift2GamesPlaceOrderService->placeOrder($orderItems, $orderNumber, $supplier->slug, $purchaseOrder);

                return [];
            }

            if ($supplier->slug === 'giftery-api') {
                return $this->gifteryPlaceOrderService->placeOrder($orderItems, $orderNumber);
            }

            throw new \RuntimeException("Unknown external supplier: {$supplier->slug}");
        } catch (\Exception $e) {
            throw $e;
        }
    }

    private function isGift2GamesSupplier(Supplier $supplier): bool
    {
        return str_starts_with($supplier->slug, 'gift2games')
            || str_starts_with($supplier->slug, 'gift-2-games');
    }
}
