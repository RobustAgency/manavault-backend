<?php

namespace App\Jobs;

use App\Models\SaleOrder;
use Spatie\WebhookServer\WebhookCall;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class SaleOrderWebhookJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct() {}

    /**
     * Execute the job.
     */
    public function handle(SaleOrder $saleOrder): void
    {
        $payload = [
            'event' => 'sale_order.completed',
            'data' => $saleOrder->toArray(),
        ];

        WebhookCall::create()
            ->url(config('webhook-server.webhook_url'))
            ->payload($payload)
            ->useSecret(config('webhook-server.webhook_secret'))
            ->dispatch();

        logger()->info(
            "Dispatched sale order webhook for order ID: {$saleOrder->id}",
            ['payload' => $payload]
        );
    }
}
