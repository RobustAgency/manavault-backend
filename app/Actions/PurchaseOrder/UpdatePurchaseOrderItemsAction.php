<?php

namespace App\Actions\PurchaseOrder;

use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderSupplier;
use App\Enums\PurchaseOrderItemStatus;
use App\Enums\PurchaseOrderSupplierStatus;
use App\Services\PurchaseOrder\PurchaseOrderStatusService;

class UpdatePurchaseOrderItemsAction
{
    public function __construct(
        private readonly PurchaseOrderStatusService $purchaseOrderStatusService,
    ) {}

    public function execute(): void
    {
        $purchaseOrderItems = PurchaseOrderItem::where('status', PurchaseOrderItemStatus::PROCESSING)->get();

        foreach ($purchaseOrderItems as $item) {
            $supplier = $item->getSupplier();

            if ($supplier === null) {
                logger()->info('UpdatePurchaseOrderItemsAction: no supplier for item, skipping', [
                    'item_id' => $item->id,
                    'supplier_id' => $item->supplier_id,
                ]);

                continue;
            }

            logger()->info('UpdatePurchaseOrderItemsAction: updating order for item', [
                'item_id' => $item->id,
                'supplier_id' => $item->supplier_id,
                'purchase_order_id' => $item->purchase_order_id,
            ]);

            $supplier->updateOrder($item);

            if ($item->status === PurchaseOrderItemStatus::FULFILLED) {
                $allCompleted = PurchaseOrderItem::where('supplier_id', $item->supplier_id)
                    ->where('purchase_order_id', $item->purchase_order_id)
                    ->get()
                    ->every(fn (PurchaseOrderItem $i) => $i->status === PurchaseOrderItemStatus::FULFILLED);

                if ($allCompleted) {
                    logger()->info('UpdatePurchaseOrderItemsAction: all items fulfilled, marking supplier completed', [
                        'supplier_id' => $item->supplier_id,
                        'purchase_order_id' => $item->purchase_order_id,
                    ]);

                    PurchaseOrderSupplier::where('supplier_id', $item->supplier_id)
                        ->where('purchase_order_id', $item->purchase_order_id)
                        ->update(['status' => PurchaseOrderSupplierStatus::COMPLETED->value]);
                }

                $this->purchaseOrderStatusService->updateStatus($item->purchaseOrder);
            }
        }

        logger()->info('UpdatePurchaseOrderItemsAction: finished');
    }
}
