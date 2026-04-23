<?php

namespace App\Contracts\Suppliers;

use App\DTOs\SupplierOrderResult;
use App\Models\PurchaseOrderSupplier;

interface SupplierOrderHandlerInterface
{
    /**
     * Place an order with the supplier.
     *
     * @throws \App\Exceptions\SupplierOrderException
     */
    public function placeOrder(PurchaseOrderSupplier $purchaseOrderSupplier): SupplierOrderResult;
}
