<?php

namespace App\Actions;

use Spatie\WebhookServer\WebhookCall;

class DispatchProductSyncWebhook
{
    public function execute(string $event, array $productIDs): void
    {
        WebhookCall::create()
            ->url(config('webhook-server.webhook_url'))
            ->payload([
                'event' => $event,
                'product_ids' => $productIDs,
            ])
            ->useSecret(config('webhook-server.webhook_secret'))
            ->dispatch();

        logger()->info(
            "Dispatched product sync webhook for event: {$event}",
            ['product_ids' => $productIDs]
        );
    }
}
