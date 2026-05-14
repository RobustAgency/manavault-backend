<?php

namespace App\Suppliers\Support;

use App\Models\Supplier;
use App\Enums\SupplierType;
use Illuminate\Support\Facades\Log;
use App\Models\PurchaseOrderSupplier;
use App\Enums\PurchaseOrderSupplierStatus;
use App\Suppliers\Contracts\PollsForVouchers;
use App\Services\PurchaseOrder\PurchaseOrderStatusService;

class SupplierVoucherPoller
{
    public function __construct(
        private SupplierIntegrationRegistry $registry,
        private VoucherWriter $voucherWriter,
        private PurchaseOrderStatusService $statusService,
    ) {}

    /**
     * Stream per-PO outcomes. With $slug omitted (or null) the poller asks the
     * registry for every external supplier whose integration implements
     * PollsForVouchers and processes them all. With $slug supplied it processes
     * only that supplier and raises if its integration is not pollable.
     *
     * Side effects (voucher persistence, status transitions, logging) happen as
     * the generator advances. Callers iterate and aggregate whatever they need.
     *
     * @return \Generator<int, PollIterationResult>
     */
    public function pollAll(?string $slug = null): \Generator
    {
        $suppliers = $slug !== null
            ? [$this->resolveSupplier($slug)]
            : $this->pollableExternalSuppliers();

        foreach ($suppliers as $supplier) {
            $integration = $this->registry->for($supplier);

            if (! $integration instanceof PollsForVouchers) {
                if ($slug !== null) {
                    throw new \LogicException("Integration for supplier '{$supplier->slug}' does not support polling.");
                }

                continue;
            }

            foreach ($this->pendingPurchaseOrderSuppliers($supplier) as $purchaseOrderSupplier) {
                yield $this->processOne($integration, $supplier, $purchaseOrderSupplier);
            }
        }
    }

    private function processOne(
        PollsForVouchers $integration,
        Supplier $supplier,
        PurchaseOrderSupplier $purchaseOrderSupplier,
    ): PollIterationResult {
        $purchaseOrder = $purchaseOrderSupplier->purchaseOrder;
        $purchaseOrder->loadMissing(['items.digitalProduct']);

        try {
            $result = $integration->pollVouchers(new PollContext(
                purchaseOrder: $purchaseOrder,
                supplier: $supplier,
                purchaseOrderSupplier: $purchaseOrderSupplier,
            ));

            if ($result->skipped) {
                return PollIterationResult::skipped($purchaseOrder, $result->reason);
            }

            $inserted = $this->voucherWriter->store($purchaseOrder, $result->vouchers);

            $purchaseOrderSupplier->update(['status' => PurchaseOrderSupplierStatus::COMPLETED->value]);
            $this->statusService->updateStatus($purchaseOrder->refresh());

            return PollIterationResult::processed($purchaseOrder, $inserted);
        } catch (\Throwable $e) {
            Log::error('Supplier voucher poll: failed to process purchase order', [
                'supplier_slug' => $supplier->slug,
                'purchase_order_id' => $purchaseOrder->id,
                'order_number' => $purchaseOrder->order_number,
                'error' => $e->getMessage(),
            ]);

            return PollIterationResult::failed($purchaseOrder, $e);
        }
    }

    private function resolveSupplier(string $slug): Supplier
    {
        return Supplier::where('slug', $slug)->firstOrFail();
    }

    /**
     * @return array<int, Supplier>
     */
    private function pollableExternalSuppliers(): array
    {
        return Supplier::where('type', SupplierType::EXTERNAL->value)
            ->get()
            ->filter(fn (Supplier $supplier) => $this->registry->has($supplier)
                && $this->registry->for($supplier) instanceof PollsForVouchers)
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, PurchaseOrderSupplier>
     */
    private function pendingPurchaseOrderSuppliers(Supplier $supplier)
    {
        return PurchaseOrderSupplier::with('purchaseOrder')
            ->where('supplier_id', $supplier->id)
            ->where('status', PurchaseOrderSupplierStatus::PROCESSING->value)
            ->whereNotNull('transaction_id')
            ->get();
    }
}
