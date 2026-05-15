<?php

namespace App\Jobs;

use App\Models\Supplier;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Log;
use App\Models\PurchaseOrderSupplier;
use App\Enums\PurchaseOrderSupplierStatus;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Services\Giftery\GifteryVoucherService;
use App\Services\Tikkery\TikkeryVoucherService;
use App\Services\Supplier\VoucherCompletenessChecker;
use App\Services\Supplier\SupplierIntegrationResolver;
use App\Services\PurchaseOrder\PurchaseOrderStatusService;
use App\Services\Supplier\SupplierVoucherPersistenceService;
use App\Services\PurchaseOrder\PurchaseOrderPlacementService;

class PlaceExternalPurchaseOrderJob implements ShouldQueue
{
    use Queueable;

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
        private readonly array $purchaseOrderItems,
        private readonly string $orderNumber,
        private readonly string $currency,
    ) {}

    public function handle(
        SupplierIntegrationResolver $resolver,
        SupplierVoucherPersistenceService $voucherPersistence,
        VoucherCompletenessChecker $completenessChecker,
        PurchaseOrderPlacementService $purchaseOrderPlacementService,
        GifteryVoucherService $gifteryVoucherService,
        TikkeryVoucherService $tikkeryVoucherService,
        PurchaseOrderStatusService $purchaseOrderStatusService,
    ): void {
        $integration = $resolver->resolve($this->supplier);

        if ($integration !== null) {
            // New abstraction path (currently: G2G suppliers)
            try {
                $result = $integration->placeOrder(
                    $this->purchaseOrderItems,
                    $this->orderNumber,
                    $this->purchaseOrder,
                    $this->supplier,
                );
            } catch (\Exception $e) {
                $this->purchaseOrderSupplier->update(['status' => PurchaseOrderSupplierStatus::FAILED->value]);
                $purchaseOrderStatusService->updateStatus($this->purchaseOrder->refresh());
                Log::error('Failed to place external order via integration', [
                    'purchase_order_id' => $this->purchaseOrder->id,
                    'supplier_slug' => $this->supplier->slug,
                    'error' => $e->getMessage(),
                ]);

                return;
            }

            $this->purchaseOrderSupplier->update(['transaction_id' => $result->transactionId]);

            Log::info('External order placed successfully via integration', [
                'supplier_slug' => $this->supplier->slug,
                'supplier_id' => $this->supplier->id,
                'transaction_id' => $result->transactionId,
            ]);

            if ($result->isComplete && ! empty($result->vouchers)) {
                $voucherPersistence->persist($this->purchaseOrder, $result->vouchers);

                $isFullyFulfilled = $completenessChecker->isComplete(
                    $this->purchaseOrder,
                    $this->purchaseOrderSupplier,
                );

                $newStatus = $isFullyFulfilled
                    ? PurchaseOrderSupplierStatus::COMPLETED->value
                    : PurchaseOrderSupplierStatus::PENDING_VOUCHERS->value;

                $this->purchaseOrderSupplier->update(['status' => $newStatus]);

            } elseif (! $result->isComplete) {
                $this->purchaseOrderSupplier->update(['status' => PurchaseOrderSupplierStatus::PENDING_VOUCHERS->value]);
                Log::info('Integration order placed, vouchers pending', [
                    'purchase_order_id' => $this->purchaseOrder->id,
                    'supplier_slug' => $this->supplier->slug,
                    'transaction_id' => $result->transactionId,
                ]);
            }

            $purchaseOrderStatusService->updateStatus($this->purchaseOrder->refresh());

            return;
        }

        // Legacy path — preserved unchanged for: Giftery, EZCards, Tikkery, Irewardify
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

        } elseif ($this->supplier->slug === 'ez_cards') {

            Log::info('EzCards order created, vouchers will be fetched separately', [
                'purchase_order_id' => $this->purchaseOrder->id,
                'transaction_id' => $transactionId,
            ]);
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
