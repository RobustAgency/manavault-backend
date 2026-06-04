<?php

namespace App\Jobs;

use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\Log;
use App\Models\PurchaseOrderSupplier;
use App\Enums\PurchaseOrderSupplierStatus;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Services\Giftery\GifteryVoucherService;
use App\Services\Tikkery\TikkeryVoucherService;
use App\Services\Supplier\SupplierIntegrationResolver;
use App\Services\PurchaseOrder\PurchaseOrderStatusService;
use App\Services\PurchaseOrder\PurchaseOrderPlacementService;

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
        private readonly PurchaseOrderSupplier $purchaseOrderSupplier,
        /** @var array<int, PurchaseOrderItem> */
        private readonly array $purchaseOrderItems,
        private readonly string $orderNumber,
        private readonly string $currency,
    ) {}

    public function handle(
        SupplierIntegrationResolver $resolver,
        PurchaseOrderPlacementService $purchaseOrderPlacementService,
        GifteryVoucherService $gifteryVoucherService,
        TikkeryVoucherService $tikkeryVoucherService,
        PurchaseOrderStatusService $purchaseOrderStatusService,
    ): void {
        // We only get items here for a single supplier.
        // grouping by supplier is done in service and then this job is dispatched.
        $integration = $resolver->resolve($this->supplier);

        if ($integration !== null) {
            // Important: We are assuming here that all purchase order items belongs to a single supplier.
            foreach ($this->purchaseOrderItems as $orderItem) {
                try {
                    $orderItem->getSupplier()?->placeOrder($orderItem);
                } catch (\Exception $e) {
                    Log::error('PlaceExternalPurchaseOrderJob: failed to place order via integration', [
                        'purchase_order_id' => $this->purchaseOrder->id,
                        'supplier_slug' => $this->supplier->slug,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return;
        }

        // ---- Legacy path: Gift2Games, Giftery, Tikkery, Irewardify ----
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

        if ($this->supplier->slug === 'giftery-api') {

            $gifteryVoucherService->storeVouchers($this->purchaseOrder, $externalOrderResponse);
            $this->purchaseOrderSupplier->update(['status' => PurchaseOrderSupplierStatus::COMPLETED->value]);

        } elseif ($this->supplier->slug === 'irewardify') {

            Log::info('Irewardify order created, vouchers will be fetched separately via orderId', [
                'purchase_order_id' => $this->purchaseOrder->id,
                'order_id' => $transactionId,
            ]);

        } elseif ($this->supplier->slug === 'tikkery') {

            $isCompleted = (bool) ($externalOrderResponse['isCompleted'] ?? false);

            if ($isCompleted && ! empty($externalOrderResponse['codes'])) {
                $tikkeryVoucherService->storeVouchers($this->purchaseOrder, $externalOrderResponse);
                $this->purchaseOrderSupplier->update(['status' => PurchaseOrderSupplierStatus::COMPLETED->value]);
            } else {
                Log::info('Tikkery order is pending, vouchers will be fetched separately', [
                    'purchase_order_id' => $this->purchaseOrder->id,
                    'transaction_id' => $transactionId,
                ]);
            }

        }

        $purchaseOrderStatusService->updateStatus($this->purchaseOrder->refresh());
    }
}
