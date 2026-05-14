<?php

namespace App\Suppliers\Support;

use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderSupplier;

final class PollContext
{
    public function __construct(
        public readonly PurchaseOrder $purchaseOrder,
        public readonly Supplier $supplier,
        public readonly PurchaseOrderSupplier $purchaseOrderSupplier,
    ) {}
}
