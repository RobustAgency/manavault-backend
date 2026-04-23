<?php

namespace App\Contracts\Suppliers;

use App\DTOs\SupplierOrderResult;
use App\Models\PurchaseOrderSupplier;

interface WebhookSupplierInterface extends SupplierOrderHandlerInterface
{
    /**
     * Handle an inbound webhook payload from the supplier.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws \App\Exceptions\SupplierOrderException
     */
    public function handleWebhook(PurchaseOrderSupplier $purchaseOrderSupplier, array $payload): SupplierOrderResult;
}
