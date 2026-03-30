<?php

namespace App\Actions;

use App\Models\SaleOrder;
use Spatie\WebhookServer\WebhookCall;

class DispatchSaleOrderWebhook
{
    public function execute(string $event, SaleOrder $saleOrder): void
    {
        WebhookCall::create()
            ->url(config('webhook-server.webhook_url'))
            ->payload([
                'event' => $event,
                'sale_order_number' => $saleOrder->order_number,
            ])
            ->useSecret(config('webhook-server.webhook_secret'))
            ->dispatch();

        logger()->info(
            "Dispatched sale order webhook for event: {$event}",
            ['sale_order_id' => $saleOrder->id]
        );
    }
}
