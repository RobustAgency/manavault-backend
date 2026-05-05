<?php

namespace App\Suppliers\Support;

use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderSupplier;

final class PlaceOrderContext
{
    /**
     * @param  array<int, \App\Models\PurchaseOrderItem>  $items
     */
    public function __construct(
        public readonly PurchaseOrder $purchaseOrder,
        public readonly Supplier $supplier,
        public readonly PurchaseOrderSupplier $purchaseOrderSupplier,
        public readonly array $items,
        public readonly string $orderNumber,
        public readonly string $currency,
    ) {}
}
