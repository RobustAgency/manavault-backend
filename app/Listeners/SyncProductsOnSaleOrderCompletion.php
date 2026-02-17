<?php

namespace App\Listeners;

use App\Events\SaleOrderCompleted;
use App\Repositories\ProductRepository;
use App\Actions\DispatchProductSyncWebhook;

class SyncProductsOnSaleOrderCompletion
{
    public function __construct(
        protected DispatchProductSyncWebhook $dispatchProductSyncWebhook,
        protected ProductRepository $productRepository
    ) {}

    /**
     * Handle the event.
     *
     * When a sale order is completed, vouchers are allocated from digital products.
     * Since a digital product can be assigned to multiple products, we need to
     * sync ALL products that share the affected digital products to Manastore.
     */
    public function handle(SaleOrderCompleted $event): void
    {
        $saleOrder = $event->saleOrder;

        $digitalProductIds = $saleOrder->items()
            ->with('digitalProducts')
            ->get()
            ->flatMap(fn ($item) => $item->digitalProducts->pluck('digital_product_id'))
            ->unique()
            ->values()
            ->toArray();

        if (empty($digitalProductIds)) {
            return;
        }

        $productIds = $this->productRepository->getProductIdsByDigitalProductIds($digitalProductIds);

        if (empty($productIds)) {
            return;
        }

        $this->dispatchProductSyncWebhook->execute(
            'sale_order.completed',
            $productIds
        );
    }
}
