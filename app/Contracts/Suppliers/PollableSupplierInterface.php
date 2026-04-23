<?php

namespace App\Contracts\Suppliers;

use App\DTOs\SupplierOrderResult;
use App\Models\PurchaseOrderSupplier;

interface PollableSupplierInterface extends SupplierOrderHandlerInterface
{
    /**
     * Poll the supplier for the current status of a previously placed order.
     *
     * @throws \App\Exceptions\SupplierOrderException
     */
    public function pollOrder(PurchaseOrderSupplier $purchaseOrderSupplier): SupplierOrderResult;
}
