<?php

namespace App\Services\Supplier;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderSupplier;
use App\Models\Voucher;

class VoucherCompletenessChecker
{
    /**
     * Returns true if every PurchaseOrderItem belonging to $poSupplier
     * has at least as many Voucher records as its requested quantity.
     */
    public function isComplete(PurchaseOrder $po, PurchaseOrderSupplier $poSupplier): bool
    {
        $items = $po->items()->where('supplier_id', $poSupplier->supplier_id)->get();

        if ($items->isEmpty()) {
            return false;
        }

        return $items->every(function ($item) use ($po) {
            return Voucher::where('purchase_order_id', $po->id)
                ->where('purchase_order_item_id', $item->id)
                ->count() >= $item->quantity;
        });
    }
}
