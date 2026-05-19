<?php

namespace App\Contracts;

use App\Models\PurchaseOrder;

interface SupplierIntegrationContract
{
    /**
     * Place an order with the supplier and return the raw response data.
     */
    public function placeOrder(array $orderItems, string $orderNumber, string $currency, PurchaseOrder $purchaseOrder): array;

    /**
     * Fetch vouchers from the supplier for a given transaction/order ID.
     */
    public function fetchVouchers(string $transactionId, PurchaseOrder $purchaseOrder): array;

    /**
     * Whether this supplier returns vouchers immediately upon order placement.
     * When false, vouchers must be polled/fetched asynchronously.
     */
    public function isVoucherReturningImmediately(): bool;

    /**
     * Sync this supplier's product catalogue to the local digital_products table.
     */
    public function syncProducts(): void;
}
