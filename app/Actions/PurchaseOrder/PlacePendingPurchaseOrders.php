<?php

namespace App\Actions\PurchaseOrder;

use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\Log;
use App\Models\PurchaseOrderSupplier;
use App\Enums\PurchaseOrderSupplierStatus;
use App\Jobs\PlaceExternalPurchaseOrderJob;
use App\Services\Supplier\SupplierIntegrationResolver;

class PlacePendingPurchaseOrders
{
    public function __construct(private readonly SupplierIntegrationResolver $resolver) {}

    public function execute(): void
    {
        $pendingSuppliers = PurchaseOrderSupplier::query()
            ->where('status', PurchaseOrderSupplierStatus::PROCESSING->value)
            ->whereNull('transaction_id')
            ->with(['supplier', 'purchaseOrder'])
            ->get();

        foreach ($pendingSuppliers as $purchaseOrderSupplier) {
            $supplier = $purchaseOrderSupplier->supplier;

            if ($this->resolver->resolve($supplier) === null) {
                continue;
            }

            $purchaseOrder = $purchaseOrderSupplier->purchaseOrder;

            $items = PurchaseOrderItem::where('purchase_order_id', $purchaseOrderSupplier->purchase_order_id)
                ->where('supplier_id', $purchaseOrderSupplier->supplier_id)
                ->with('digitalProduct')
                ->get()
                ->all();

            try {
                PlaceExternalPurchaseOrderJob::dispatch(
                    $purchaseOrder,
                    $supplier,
                    $purchaseOrderSupplier,
                    $items,
                    $purchaseOrder->order_number,
                    $purchaseOrder->currency,
                );

                Log::info('PlacePendingPurchaseOrders: dispatched job', [
                    'supplier' => $supplier->slug,
                    'purchase_order_id' => $purchaseOrder->id,
                    'purchase_order_supplier_id' => $purchaseOrderSupplier->id,
                ]);
            } catch (\Throwable $e) {
                Log::error('PlacePendingPurchaseOrders: failed to dispatch job', [
                    'supplier' => $supplier->slug,
                    'purchase_order_id' => $purchaseOrder->id,
                    'purchase_order_supplier_id' => $purchaseOrderSupplier->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
