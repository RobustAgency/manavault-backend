<?php

namespace App\Actions;

use App\Models\Product;
use Spatie\WebhookServer\WebhookCall;
use App\Http\Resources\ProductResource;

class DispatchProductSyncWebhook
{
    public function execute(string $event, int $productId): void
    {
        $product = Product::with(['brand', 'digitalProducts.supplier'])->find($productId);

        if (! $product) {
            return;
        }

        $this->dispatch($event, $product->id, (new ProductResource($product))->toArray(request()));
    }

    /**
     * The product row is already gone by the time the "deleted" event fires, so there's no
     * resource left to fetch. Notify with just the id instead of re-querying.
     */
    public function executeForDeletion(string $event, int $productId): void
    {
        $this->dispatch($event, $productId, ['id' => $productId]);
    }

    private function dispatch(string $event, int $productId, array $data): void
    {
        WebhookCall::create()
            ->url(config('webhook-server.webhook_url'))
            ->payload([
                'event' => $event,
                'data' => $data,
            ])
            ->useSecret(config('webhook-server.webhook_secret'))
            ->dispatch();

        logger()->info(
            "Dispatched product sync webhook for event: {$event}",
            ['product_id' => $productId]
        );
    }
}
