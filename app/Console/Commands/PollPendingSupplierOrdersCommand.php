<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PurchaseOrderSupplier;
use App\Managers\SupplierOrderManager;
use App\Enums\PurchaseOrderSupplierStatus;
use App\Exceptions\SupplierOrderException;
use App\Services\PurchaseOrder\PurchaseOrderStatusService;

class PollPendingSupplierOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'suppliers:poll-pending
                            {--slug= : Poll only a specific supplier slug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll all pollable suppliers for pending purchase order fulfillment';

    public function __construct(
        private readonly SupplierOrderManager $supplierOrderManager,
        private readonly PurchaseOrderStatusService $purchaseOrderStatusService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filterSlug = $this->option('slug');

        $pollableDrivers = $this->supplierOrderManager->getPollableDrivers();

        if (empty($pollableDrivers)) {
            $this->warn('No pollable supplier drivers registered.');

            return self::SUCCESS;
        }

        if ($filterSlug && ! isset($pollableDrivers[$filterSlug])) {
            $this->error("Supplier slug '{$filterSlug}' is not registered as a pollable driver.");

            return self::FAILURE;
        }

        $driversToRun = $filterSlug
            ? [$filterSlug => $pollableDrivers[$filterSlug]]
            : $pollableDrivers;

        $totalProcessed = 0;
        $totalCompleted = 0;
        $totalSkipped = 0;
        $totalFailed = 0;

        foreach ($driversToRun as $slug => $driver) {
            $this->info("Polling supplier: {$slug}");

            $pendingSupplierOrders = PurchaseOrderSupplier::query()
                ->whereHas('supplier', fn ($q) => $q->where('slug', $slug))
                ->where('status', PurchaseOrderSupplierStatus::PROCESSING->value)
                ->whereNotNull('transaction_id')
                ->with('purchaseOrder')
                ->get();

            if ($pendingSupplierOrders->isEmpty()) {
                $this->line("  No pending orders for {$slug}.");

                continue;
            }

            $this->line("  Found {$pendingSupplierOrders->count()} pending order(s).");

            foreach ($pendingSupplierOrders as $purchaseOrderSupplier) {
                $totalProcessed++;

                try {
                    $result = $driver->pollOrder($purchaseOrderSupplier);

                    if ($result->isCompleted()) {
                        $totalCompleted++;
                        $this->line("  ✔ PurchaseOrderSupplier #{$purchaseOrderSupplier->id} completed.");
                        $this->purchaseOrderStatusService->updateStatus(
                            $purchaseOrderSupplier->purchaseOrder->refresh()
                        );
                    } elseif ($result->isFailed()) {
                        $totalFailed++;
                        $this->warn("  ✘ PurchaseOrderSupplier #{$purchaseOrderSupplier->id} failed.");
                        $this->purchaseOrderStatusService->updateStatus(
                            $purchaseOrderSupplier->purchaseOrder->refresh()
                        );
                    } else {
                        $totalSkipped++;
                        $this->line("  ⏳ PurchaseOrderSupplier #{$purchaseOrderSupplier->id} still processing.");
                    }
                } catch (SupplierOrderException $e) {
                    $totalFailed++;
                    $this->error("  Error polling #{$purchaseOrderSupplier->id}: {$e->getMessage()}");
                }
            }
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Supplier(s) polled', count($driversToRun)],
                ['Orders checked', $totalProcessed],
                ['Completed', $totalCompleted],
                ['Still processing', $totalSkipped],
                ['Failed', $totalFailed],
            ]
        );

        return self::SUCCESS;
    }
}
