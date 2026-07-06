<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Actions\PurchaseOrder\ExtractAvailableVouchersToNewPurchaseOrder;

class ExtractAvailableVouchersCommand extends Command
{
    protected $signature = 'purchase-order:extract-available-vouchers
                            {purchase_order_id : The ID of the source purchase order to extract available vouchers from}
                            {--dry-run : Report what would happen without writing any changes}';

    protected $description = 'Move the available (un-allocated) vouchers of a purchase order onto a fresh general-stock purchase order with matching line items.';

    public function handle(ExtractAvailableVouchersToNewPurchaseOrder $action): int
    {
        $purchaseOrderId = (int) $this->argument('purchase_order_id');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be persisted.');
        }

        try {
            $summary = $action->execute($purchaseOrderId, $dryRun);
        } catch (\Throwable $e) {
            Log::error('ExtractAvailableVouchersCommand: failed', [
                'purchase_order_id' => $purchaseOrderId,
                'error' => $e->getMessage(),
            ]);
            $this->error('Error: '.$e->getMessage());

            return Command::FAILURE;
        }

        if ($summary['vouchers_moved'] === 0) {
            $this->info("No available vouchers to extract from purchase order #{$purchaseOrderId}.");
            $this->warnSkipped($summary['skipped']);

            return Command::SUCCESS;
        }

        $this->table(
            ['Digital Product ID', 'Digital Product', 'Vouchers Moved'],
            array_map(fn (array $row) => [
                $row['digital_product_id'],
                $row['digital_product_name'],
                $row['quantity'],
            ], $summary['breakdown']),
        );

        if ($dryRun) {
            $this->info("DRY RUN — would move {$summary['vouchers_moved']} voucher(s) into a new purchase order across {$summary['items_created']} item(s). Nothing persisted.");
        } else {
            $this->info("Moved {$summary['vouchers_moved']} voucher(s) into new purchase order #{$summary['new_purchase_order_id']} ({$summary['new_order_number']}).");
        }

        $this->warnSkipped($summary['skipped']);

        return Command::SUCCESS;
    }

    private function warnSkipped(int $skipped): void
    {
        if ($skipped > 0) {
            $this->warn("{$skipped} voucher(s) skipped (no linked purchase order item) and left on the source order.");
        }
    }
}
