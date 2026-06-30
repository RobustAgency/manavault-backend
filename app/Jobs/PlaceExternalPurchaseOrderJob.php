<?php

namespace App\Jobs;

use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\Log;
use App\Models\PurchaseOrderSupplier;
use Illuminate\Support\Facades\Cache;
use App\Enums\PurchaseOrderSupplierStatus;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use App\Services\Supplier\SupplierIntegrationResolver;
use App\Services\PurchaseOrder\PurchaseOrderStatusService;
use App\Services\PurchaseOrder\PurchaseOrderPlacementService;

class PlaceExternalPurchaseOrderJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(
        private readonly PurchaseOrder $purchaseOrder,
        private readonly Supplier $supplier,
        private readonly PurchaseOrderSupplier $purchaseOrderSupplier,
        /** @var array<int, PurchaseOrderItem> */
        private readonly array $purchaseOrderItems,
        private readonly string $orderNumber,
        private readonly string $currency,
    ) {}

    /**
     * Ensure only one job runs at a time for a given purchase order.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->purchaseOrder->id))
                ->dontRelease()
                ->expireAfter($this->timeout),
        ];
    }

    public function handle(
        SupplierIntegrationResolver $resolver,
        PurchaseOrderPlacementService $purchaseOrderPlacementService,
        PurchaseOrderStatusService $purchaseOrderStatusService,
    ): void {
        // We only get items here for a single supplier.
        // grouping by supplier is done in service and then this job is dispatched.
        $integration = $resolver->resolve($this->supplier);

        if ($integration !== null) {
            // Important: We are assuming here that all purchase order items belongs to a single supplier.
            foreach ($this->purchaseOrderItems as $orderItem) {
                try {
                    Cache::lock("place-external-purchase-order-item:{$orderItem->id}", $this->timeout)
                        ->get(function () use ($orderItem) {
                            $orderItem->refresh();

                            return $orderItem->getSupplier()?->placeOrder($orderItem);
                        });
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

            return;
        }

        // ---- Legacy path: Irewardify ----
        $externalOrderResponse = [];
        $transactionId = null;

        try {
            $externalOrderResponse = $purchaseOrderPlacementService->placeOrder(
                $this->supplier,
                $this->purchaseOrderItems,
                $this->orderNumber,
                $this->currency,
                $this->purchaseOrder,
            );

            $transactionId = $externalOrderResponse['transactionId'] ?? null;

            $this->purchaseOrderSupplier->update(['transaction_id' => $transactionId]);

            Log::info('External order placed successfully', [
                'supplier_slug' => $this->supplier->slug,
                'supplier_id' => $this->supplier->id,
                'transaction_id' => $transactionId,
            ]);
        } catch (\Exception $e) {
            $this->purchaseOrderSupplier->update(['status' => PurchaseOrderSupplierStatus::FAILED->value]);
            $purchaseOrderStatusService->updateStatus($this->purchaseOrder->refresh());
            Log::error('Failed to place external order', [
                'purchase_order_id' => $this->purchaseOrder->id,
                'supplier_slug' => $this->supplier->slug,
                'supplier_id' => $this->supplier->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if ($this->supplier->slug === 'irewardify') {

            Log::info('Irewardify order created, vouchers will be fetched separately via orderId', [
                'purchase_order_id' => $this->purchaseOrder->id,
                'order_id' => $transactionId,
            ]);
        }

        $purchaseOrderStatusService->updateStatus($this->purchaseOrder->refresh());
    }
}
