<?php

namespace App\Services;

use App\Models\Product;
use App\Enums\SupplierType;
use App\Models\DigitalProduct;

class AutoPurchaseOrderService
{
    public function __construct(

        private PurchaseOrderService $purchaseOrderService,
    ) {}

    /**
     * Create purchase orders to cover the shortfall for a product from eligible external suppliers.
     *
     * Returns true if at least one external PO was dispatched, false if no eligible supplier exists.
     */
    public function handleShortfall(DigitalProduct $digitalProduct, int $shortfall): bool
    {
        $digitalProduct->load('supplier');
        $supplier = $digitalProduct->supplier;

        if (! $supplier || $supplier->type !== SupplierType::EXTERNAL->value) {
            return false;
        }

        $this->purchaseOrderService->createPurchaseOrderForDigitalProduct($digitalProduct, $shortfall);

        return true;

    }
}
