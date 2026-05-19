<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderSupplier;
use App\Enums\PurchaseOrderSupplierStatus;
use App\Services\Supplier\SupplierIntegrationResolver;

class PlacePendingPurchaseOrdersCommand extends Command
{
    protected $signature = 'purchase-order:place-pending';

    protected $description = 'Place orders for pending purchase order suppliers that have no transaction ID';

    public function __construct(private readonly SupplierIntegrationResolver $resolver)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $hasFailures = false;

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
            $integration = $this->resolver->resolve($supplier);

            if ($integration === null) {

                continue;
            }

            $purchaseOrder = $purchaseOrderSupplier->purchaseOrder;
            $items = PurchaseOrderItem::where('purchase_order_id', $purchaseOrderSupplier->purchase_order_id)
                ->where('supplier_id', $purchaseOrderSupplier->supplier_id)
                ->with('digitalProduct')
                ->get();

            try {
                $response = $integration->placeOrder(
                    $items->all(),
                    $purchaseOrder->order_number,
                    $purchaseOrder->currency,
                    $purchaseOrder,
                );

                $transactionId = $response['transactionId'] ?? null;

                $purchaseOrderSupplier->update(['transaction_id' => $transactionId]);

                Log::info('Pending purchase order placed successfully', [
                    'supplier' => $supplier->slug,
                    'purchase_order_supplier_id' => $purchaseOrderSupplier->id,
                    'purchase_order_id' => $purchaseOrder->id,
                    'order_number' => $purchaseOrder->order_number,
                    'transaction_id' => $transactionId,
                ]);

            } catch (\Throwable $e) {
                $hasFailures = true;

                $purchaseOrderSupplier->update(['status' => PurchaseOrderSupplierStatus::FAILED->value]);

                Log::error('Failed to place pending purchase order', [
                    'supplier' => $supplier->slug,
                    'purchase_order_supplier_id' => $purchaseOrderSupplier->id,
                    'purchase_order_id' => $purchaseOrder->id,
                    'order_number' => $purchaseOrder->order_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $hasFailures ? Command::FAILURE : Command::SUCCESS;
    }
}
