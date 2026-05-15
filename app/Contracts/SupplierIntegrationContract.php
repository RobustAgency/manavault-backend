<?php

namespace App\Contracts;

use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderSupplier;
use App\DTOs\Supplier\VoucherFetchResult;
use App\DTOs\Supplier\SupplierOrderResult;

interface SupplierIntegrationContract
{
    /**
     * Place an order for the given items and return a standardised result.
     *
     * When isVoucherDeliveryImmediate() is true, the returned SupplierOrderResult
     * will have isComplete=true and its $vouchers array populated.
     * When false, $vouchers will be empty and the job marks the supplier
     * as PENDING_VOUCHERS for later polling via fetchPendingVouchers().
     *
     * @param  \App\Models\PurchaseOrderItem[]  $items
     */
    public function placeOrder(
        array $items,
        string $orderNumber,
        PurchaseOrder $po,
        Supplier $supplier,
    ): SupplierOrderResult;

    /**
     * Whether the supplier returns vouchers synchronously inside placeOrder().
     */
    public function isVoucherDeliveryImmediate(): bool;

    /**
     * Poll the supplier for pending vouchers (only called when isVoucherDeliveryImmediate() is false).
     */
    public function fetchPendingVouchers(
        PurchaseOrder $po,
        PurchaseOrderSupplier $poSupplier,
    ): VoucherFetchResult;

    /**
     * Sync this supplier's product catalogue to the local digital_products table.
     */
    public function syncProducts(): void;
}
