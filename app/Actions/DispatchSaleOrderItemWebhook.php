<?php

namespace App\Actions;

use App\Models\SaleOrderItem;
use Illuminate\Support\Facades\DB;
use Spatie\WebhookServer\WebhookCall;

class DispatchSaleOrderItemWebhook
{
    public function execute(string $event, SaleOrderItem $saleOrderItem): void
    {
        $saleOrderItem->loadMissing('saleOrder');

        $payload = [
            'event' => $event,
            'sale_order_number' => $saleOrderItem->saleOrder->order_number,
            'product_id' => $saleOrderItem->product_id,
            'quantity' => $saleOrderItem->quantity,
            'status' => $saleOrderItem->status->value,
        ];

        // Allocation runs inside transactions that are rolled back. Defer the dispatch so a rolled-back allocation never
        // notifies Manastore. Runs immediately when no transaction is open.
        DB::afterCommit(function () use ($payload, $event, $saleOrderItem) {
            WebhookCall::create()
                ->url(config('webhook-server.webhook_url'))
                ->payload($payload)
                ->useSecret(config('webhook-server.webhook_secret'))
                ->dispatch();

            logger()->info(
                "Dispatched sale order item webhook for event: {$event}",
                ['sale_order_item_id' => $saleOrderItem->id]
            );
        });
    }
}
