<?php

namespace App\Console\Commands;

use App\Models\SaleOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Actions\AssignVouchersToSaleOrderAction;

class AssignVouchersToSaleOrderCommand extends Command
{
    protected $signature = 'orders:assign-vouchers {sale_order_id : The ID of the sale order to process}';

    protected $description = 'Assign available vouchers to a sale order using the digital product selected for each item at order creation time';

    public function __construct(
        private AssignVouchersToSaleOrderAction $action,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $saleOrderId = (int) $this->argument('sale_order_id');

        $saleOrder = SaleOrder::with(['items.product', 'items.selectedDigitalProduct', 'items.digitalProducts'])
            ->find($saleOrderId);

        if (! $saleOrder) {
            $this->error("Sale order with ID {$saleOrderId} not found.");

            return Command::FAILURE;
        }

        $this->info("Processing sale order #{$saleOrder->order_number} (ID: {$saleOrder->id})");
        $this->line("Current status: {$saleOrder->status}");
        $this->newLine();

        try {
            $result = $this->action->execute($saleOrder);
        } catch (\Exception $e) {
            Log::error('AssignVouchersToSaleOrderCommand: failed to process order', [
                'sale_order_id' => $saleOrderId,
                'error' => $e->getMessage(),
            ]);
            $this->error('Error: '.$e->getMessage());

            return Command::FAILURE;
        }

        if ($result['already_completed']) {
            $this->warn('Sale order is already completed. No allocation needed.');

            return Command::SUCCESS;
        }

        $this->table(
            ['Product', 'Qty Ordered', 'Previously Allocated', 'Newly Allocated', 'Status'],
            $result['summary'],
        );
        $this->newLine();

        if ($result['fully_allocated']) {
            $this->info('Sale order fully allocated and marked as COMPLETED.');

            return Command::SUCCESS;
        }

        $this->warn('Insufficient voucher stock to fully allocate this order. No changes were saved.');
        $this->warn('Add more vouchers to stock and retry.');

        return Command::FAILURE;
    }
}
