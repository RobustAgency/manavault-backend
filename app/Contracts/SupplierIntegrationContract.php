<?php

namespace App\Contracts;

use App\Models\PurchaseOrderItem;

interface SupplierIntegrationContract
{
    /**
     * Place an order with the supplier and return the raw response data.
     */
    public function placeOrder(PurchaseOrderItem $purchaseOrderItem): void;

    /**
     * Update an existing order with the supplier.
     */
    public function updateOrder(PurchaseOrderItem $purchaseOrderItem): void;

    /**
     * Sync this supplier's product catalogue to the local digital_products table.
     */
    public function syncProducts(): void;
}
