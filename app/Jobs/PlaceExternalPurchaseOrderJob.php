<?php

namespace App\Jobs;

use App\Models\Supplier;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Log;
use App\Models\PurchaseOrderSupplier;
use App\Enums\PurchaseOrderSupplierStatus;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Services\Gift2Games\Gift2GamesVoucherService;
use App\Services\PurchaseOrder\PurchaseOrderStatusService;
use App\Services\PurchaseOrder\PurchaseOrderPlacementService;

class PlaceExternalPurchaseOrderJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
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
        PurchaseOrderPlacementService $purchaseOrderPlacementService,
        Gift2GamesVoucherService $gift2GamesVoucherService,
        PurchaseOrderStatusService $purchaseOrderStatusService,
    ): void {
        $externalOrderResponse = [];
        $transactionId = null;

        try {
            $externalOrderResponse = $purchaseOrderPlacementService->placeOrder(
                $this->supplier,
                $this->purchaseOrderItems,
                $this->orderNumber,
                $this->currency,
            );

            $transactionId = $externalOrderResponse['transactionId'] ?? null;

            $this->purchaseOrderSupplier->update(['transaction_id' => $transactionId]);

            if ($this->isGift2GamesSupplier()) {
                $gift2GamesVoucherService->storeVouchers($this->purchaseOrder, $externalOrderResponse);
            }

            $this->purchaseOrderSupplier->update(['status' => PurchaseOrderSupplierStatus::COMPLETED->value]);

            $purchaseOrderStatusService->updateStatus($this->purchaseOrder->refresh());

            Log::info('External order placed successfully', [
                'supplier_slug' => $this->supplier->slug,
                'supplier_id' => $this->supplier->id,
                'transaction_id' => $transactionId,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to place external order', [
                'purchase_order_id' => $this->purchaseOrder->id,
                'supplier_slug' => $this->supplier->slug,
                'supplier_id' => $this->supplier->id,
                'error' => $e->getMessage(),
            ]);

            $this->purchaseOrderSupplier->update(['status' => PurchaseOrderSupplierStatus::FAILED->value]);

            $purchaseOrderStatusService->updateStatus($this->purchaseOrder->refresh());
        }
    }

    private function isGift2GamesSupplier(): bool
    {
        return str_starts_with($this->supplier->slug, 'gift2games')
            || str_starts_with($this->supplier->slug, 'gift-2-games');
    }
}
