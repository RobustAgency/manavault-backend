<?php

namespace App\Jobs;

use App\Models\Supplier;
use App\Enums\SupplierSlug;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderSupplier;
use App\Managers\SupplierOrderManager;
use App\Exceptions\SupplierOrderException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Services\PurchaseOrder\PurchaseOrderStatusService;

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
    ) {}

    public function handle(
        SupplierOrderManager $supplierOrderManager,
        PurchaseOrderStatusService $purchaseOrderStatusService,
    ): void {
        if (SupplierSlug::tryFrom($this->supplier->slug) !== null) {
            try {
                $result = $supplierOrderManager
                    ->driver($this->supplier->slug)
                    ->placeOrder($this->purchaseOrderSupplier);
            } catch (SupplierOrderException $e) {
                $purchaseOrderStatusService->updateStatus($this->purchaseOrder->refresh());

                return;
            }

            if ($result->isFailed()) {
                $purchaseOrderStatusService->updateStatus($this->purchaseOrder->refresh());

                return;
            }

            $purchaseOrderStatusService->updateStatus($this->purchaseOrder->refresh());

            return;
        }
    }
}
