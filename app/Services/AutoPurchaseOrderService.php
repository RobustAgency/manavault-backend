<?php

namespace App\Services;

use App\Models\DigitalProduct;

class AutoPurchaseOrderService
{
    public function __construct(

        private PurchaseOrderService $purchaseOrderService,
    ) {}

    public function handleShortfall(DigitalProduct $digitalProduct, int $shortfall, ?int $saleOrderId = null): void
    {
        logger()->info("Handling shortfall for DigitalProduct ID: {$digitalProduct->id}, Shortfall: {$shortfall}");
        $digitalProduct->load('supplier');
        $this->purchaseOrderService->createPurchaseOrderForDigitalProduct($digitalProduct, $shortfall, $saleOrderId);
        logger()->info("Created purchase order for DigitalProduct ID: {$digitalProduct->id}, Quantity: {$shortfall}");
    }
}
