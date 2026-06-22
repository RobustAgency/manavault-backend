<?php

namespace App\Console\Commands;

use App\Enums\SaleOrder\Status;
use Illuminate\Console\Command;
use App\Actions\SaleOrder\FulfillProcessingSaleOrders;

class FulfillProcessingSaleOrdersCommand extends Command
{
    protected $signature = 'sale-order:fulfill-processing
                            {--id= : Process a single sale order by ID}
                            {--dry-run : Report what would happen without writing any changes}';

    protected $description = 'Replay fulfillment for PROCESSING sale orders: allocate from general stock, then create linked purchase orders for any remaining shortfall.';

    public function handle(FulfillProcessingSaleOrders $action): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $saleOrderId = $this->option('id') !== null ? (int) $this->option('id') : null;

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be persisted.');
        }

        logger()->info('FulfillProcessingSaleOrdersCommand: started', [
            'dry_run' => $dryRun,
            'sale_order_id' => $saleOrderId,
        ]);

        $summary = $action->execute($dryRun, $saleOrderId);

        if (empty($summary)) {
            $this->info('No processing sale orders found.');

            return Command::SUCCESS;
        }

        $this->renderSummary($summary);

        logger()->info('FulfillProcessingSaleOrdersCommand: finished', [
            'orders_processed' => count($summary),
        ]);

        return Command::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $summary
     */
    private function renderSummary(array $summary): void
    {
        $rows = array_map(fn (array $row) => [
            $row['sale_order_id'],
            $row['order_number'],
            $row['status'],
            $row['allocated'],
            $row['purchase_orders_created'],
            $row['error'] ?? '',
        ], $summary);

        $this->table(
            ['Sale Order', 'Order #', 'Result', 'Vouchers Allocated', 'POs Created', 'Error'],
            $rows,
        );

        $summaryCollection = collect($summary);
        $completed = $summaryCollection->where('status', Status::COMPLETED->value)->count();
        $processing = $summaryCollection->where('status', Status::PROCESSING->value)->count();
        $errored = $summaryCollection->where('status', 'error')->count();

        $this->info("{$completed} completed, {$processing} still processing, {$errored} errored.");
    }
}
