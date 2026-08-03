<?php

namespace App\Actions;

use App\Models\SaleOrderItem;
use Spatie\WebhookServer\WebhookCall;
use App\Http\Resources\SaleOrderItemResource;

class DispatchSaleOrderItemWebhook
{
    public function execute(string $event, SaleOrderItem $saleOrderItem): void
    {
        $saleOrderItem->loadMissing(['saleOrder', 'product']);

        WebhookCall::create()
            ->url(config('webhook-server.webhook_url'))
            ->payload([
                'event' => $event,
                'data' => (new SaleOrderItemResource($saleOrderItem))->toArray(request()),
            ])
            ->useSecret(config('webhook-server.webhook_secret'))
            ->dispatch();

        logger()->info(
            "Dispatched sale order item webhook for event: {$event}",
            ['sale_order_item_id' => $saleOrderItem->id]
        );
    }
}
