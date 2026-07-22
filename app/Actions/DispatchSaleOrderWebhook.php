<?php

namespace App\Actions;

use App\Models\SaleOrder;
use Illuminate\Support\Facades\DB;
use Spatie\WebhookServer\WebhookCall;

class DispatchSaleOrderWebhook
{
    public function execute(string $event, SaleOrder $saleOrder): void
    {
        // Fulfillment runs inside transactions that are rolled back when an order cannot be
        // fully allocated, and queued jobs are not deferred to commit (config/queue.php sets
        // after_commit => false). Defer the dispatch so a rolled-back fulfillment never
        // notifies Manastore. Runs immediately when no transaction is open.
        DB::afterCommit(function () use ($event, $saleOrder) {
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
        });
    }
}
