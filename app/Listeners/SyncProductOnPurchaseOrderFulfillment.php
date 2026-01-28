<?php

namespace App\Listeners;

use App\Events\PurchaseOrderFulfill;
use App\Repositories\ProductRepository;
use App\Actions\DispatchProductSyncWebhook;

class SyncProductOnPurchaseOrderFulfillment
{
    public function __construct(
        protected DispatchProductSyncWebhook $dispatchProductSyncWebhook,
        protected ProductRepository $productRepository
    ) {}

    /**
     * Handle the event.
     */
    public function handle(PurchaseOrderFulfill $event): void
    {
        $purchaseOrder = $event->purchaseOrder;
        $digitalProducts = $purchaseOrder->items()
            ->with('digitalProduct')
            ->get()
            ->pluck('digitalProduct');

        $digitalProductIds = $digitalProducts->pluck('id')->toArray();

        $productIds = $this->productRepository->getProductIdsByDigitalProductIds($digitalProductIds);

        $this->dispatchProductSyncWebhook->execute(
            'purchase_order.fulfilled',
            $productIds
        );
    }
}
