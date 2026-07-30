<?php

namespace App\Actions;

use App\Models\SaleOrder;
use Spatie\WebhookServer\WebhookCall;
use App\Http\Resources\ManaStore\V1\SaleOrderResource;

class DispatchSaleOrderWebhook
{
    public function execute(string $event, SaleOrder $saleOrder): void
    {
        WebhookCall::create()
            ->url(config('webhook-server.webhook_url'))
            ->payload([
                'event' => $event,
                'data' => (new SaleOrderResource($saleOrder))->toArray(request()),
            ])
            ->useSecret(config('webhook-server.webhook_secret'))
            ->dispatch();

        logger()->info(
            "Dispatched sale order webhook for event: {$event}",
            ['sale_order_id' => $saleOrder->id]
        );
    }
}
