<?php

namespace App\Console\Commands;

use App\Enums\SupplierType;
use App\Models\PurchaseOrder;
use Illuminate\Console\Command;
use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrderSupplier;
use App\Enums\PurchaseOrderSupplierStatus;
use App\Jobs\PlaceExternalPurchaseOrderJob;

class RetryFailedPurchaseOrdersCommand extends Command
{
    protected $signature = 'purchase-order:retry-failed
                            {--id= : Retry a specific purchase order by ID}';

    protected $description = 'Retry failed supplier jobs for purchase orders in failed or processing state';

    public function handle(): int
    {
        $purchaseOrderId = $this->option('id');

        $query = PurchaseOrder::query()
            ->whereIn('status', [
                PurchaseOrderStatus::FAILED->value,
                PurchaseOrderStatus::PROCESSING->value,
            ])
            ->with([
                'purchaseOrderSuppliers' => fn ($q) => $q
                    ->where('status', PurchaseOrderSupplierStatus::FAILED->value)
                    ->with(['supplier', 'purchaseOrderItems']),
            ]);

        if ($purchaseOrderId) {
            $query->where('id', $purchaseOrderId);
        }

        $purchaseOrders = $query->get();

        if ($purchaseOrders->isEmpty()) {
            $this->info('No purchase orders found to retry.');

            return Command::SUCCESS;
        }

        foreach ($purchaseOrders as $purchaseOrder) {
            $failedSuppliers = $purchaseOrder->purchaseOrderSuppliers->filter(
                fn (PurchaseOrderSupplier $pos) => $pos->supplier?->type === SupplierType::EXTERNAL->value
            );

            if ($failedSuppliers->isEmpty()) {
                continue;
            }

            $this->info("PO #{$purchaseOrder->id} ({$purchaseOrder->order_number}): retrying {$failedSuppliers->count()} failed supplier(s)...");

            if ($purchaseOrder->status === PurchaseOrderStatus::FAILED->value) {
                $purchaseOrder->update(['status' => PurchaseOrderStatus::PROCESSING->value]);
            }

            foreach ($failedSuppliers as $purchaseOrderSupplier) {
                $supplier = $purchaseOrderSupplier->supplier;
                $items = $purchaseOrderSupplier->purchaseOrderItems->all();

                if (empty($items)) {
                    $this->warn("  Supplier {$supplier->slug}: no items found, skipping.");

                    continue;
                }

                $purchaseOrderSupplier->update(['status' => PurchaseOrderSupplierStatus::PROCESSING->value]);

                PlaceExternalPurchaseOrderJob::dispatch(
                    $purchaseOrder,
                    $supplier,
                    $purchaseOrderSupplier,
                    $items,
                    $purchaseOrder->order_number,
                    $purchaseOrder->currency,
                );
            }
        }

        return Command::SUCCESS;
    }
}
