<?php

namespace App\Jobs;

use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Services\Supplier\SupplierIntegrationResolver;

class PlaceExternalPurchaseOrderJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    public int $timeout = 600;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 60, 120];

    public function __construct(
        private readonly PurchaseOrder $purchaseOrder,
        private readonly Supplier $supplier,
        /** @var array<int, PurchaseOrderItem> */
        private readonly array $purchaseOrderItems,
    ) {}

    public function handle(SupplierIntegrationResolver $resolver): void
    {
        // We only get items here for a single supplier.
        // grouping by supplier is done in service and then this job is dispatched.
        $integration = $resolver->resolve($this->supplier);

        if ($integration === null) {
            Log::error('PlaceExternalPurchaseOrderJob: no integration registered for supplier', [
                'purchase_order_id' => $this->purchaseOrder->id,
                'supplier_slug' => $this->supplier->slug,
            ]);

            return;
        }

        // Important: We are assuming here that all purchase order items belongs to a single supplier.
        foreach ($this->purchaseOrderItems as $orderItem) {
            try {
                $orderItem->getSupplier()?->placeOrder($orderItem);
            } catch (\Exception $e) {
                $cause = $e->getPrevious() ?? $e;
                $responseBody = $cause instanceof \Illuminate\Http\Client\RequestException
                    ? $cause->response->body()
                    : null;

                Log::error('PlaceExternalPurchaseOrderJob: failed to place order via integration', [
                    'purchase_order_id' => $this->purchaseOrder->id,
                    'purchase_order_item_id' => $orderItem->id,
                    'supplier_slug' => $this->supplier->slug,
                    'error' => $e->getMessage(),
                    'response_body' => $responseBody,
                ]);
            }
        }
    }
}
