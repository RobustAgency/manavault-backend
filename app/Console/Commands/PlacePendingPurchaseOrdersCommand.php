<?php

namespace App\Console\Commands;

use App\Enums\PurchaseOrderSupplierStatus;
use App\Jobs\PlaceExternalPurchaseOrderJob;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderSupplier;
use App\Services\Supplier\SupplierIntegrationResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PlacePendingPurchaseOrdersCommand extends Command
{
    protected $signature = 'purchase-order:place-pending';

    protected $description = 'Dispatch order-placement jobs for pending purchase order suppliers registered in the integration layer';

    public function __construct(private readonly SupplierIntegrationResolver $resolver)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $hasFailures = false;

        // Find supplier records that have no transaction_id yet — order hasn't been placed.
        // PENDING_VOUCHERS records already have a transaction_id, so the atomic job will
        // skip re-placement and only sync status when re-dispatched; those are not targeted here.
        $pendingSuppliers = PurchaseOrderSupplier::query()
            ->where('status', PurchaseOrderSupplierStatus::PROCESSING->value)
            ->whereNull('transaction_id')
            ->with(['supplier', 'purchaseOrder'])
            ->get();

        if ($pendingSuppliers->isEmpty()) {
            $this->info('No pending purchase orders to place.');

            return Command::SUCCESS;
        }

        foreach ($pendingSuppliers as $purchaseOrderSupplier) {
            $supplier = $purchaseOrderSupplier->supplier;

            if ($this->resolver->resolve($supplier) === null) {
                continue;
            }

            $purchaseOrder = $purchaseOrderSupplier->purchaseOrder;

            $items = PurchaseOrderItem::where('purchase_order_id', $purchaseOrderSupplier->purchase_order_id)
                ->where('supplier_id', $purchaseOrderSupplier->supplier_id)
                ->with('digitalProduct')
                ->get()
                ->all();

            try {
                PlaceExternalPurchaseOrderJob::dispatch(
                    $purchaseOrder,
                    $supplier,
                    $purchaseOrderSupplier,
                    $items,
                    $purchaseOrder->order_number,
                    $purchaseOrder->currency,
                );

                Log::info('PlacePendingPurchaseOrdersCommand: dispatched job', [
                    'supplier'                  => $supplier->slug,
                    'purchase_order_id'         => $purchaseOrder->id,
                    'purchase_order_supplier_id' => $purchaseOrderSupplier->id,
                ]);
            } catch (\Throwable $e) {
                $hasFailures = true;

                Log::error('PlacePendingPurchaseOrdersCommand: failed to dispatch job', [
                    'supplier'                  => $supplier->slug,
                    'purchase_order_id'         => $purchaseOrder->id,
                    'purchase_order_supplier_id' => $purchaseOrderSupplier->id,
                    'error'                     => $e->getMessage(),
                ]);
            }
        }

        return $hasFailures ? Command::FAILURE : Command::SUCCESS;
    }
}
