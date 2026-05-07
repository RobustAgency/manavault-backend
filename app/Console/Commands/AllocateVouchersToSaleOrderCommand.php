<?php

namespace App\Console\Commands;

use App\Enums\SaleOrder\Status;
use App\Events\SaleOrderCompleted;
use App\Models\SaleOrder;
use App\Repositories\SaleOrderRepository;
use App\Services\DigitalProductAllocationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AllocateVouchersToSaleOrderCommand extends Command
{
    protected $signature = 'orders:allocate-vouchers {sale_order_id : The ID of the sale order to allocate vouchers for}';

    protected $description = 'Manually allocate available vouchers to a specific sale order';

    public function __construct(
        private SaleOrderRepository $saleOrderRepository,
        private DigitalProductAllocationService $digitalProductAllocationService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $saleOrderId = (int) $this->argument('sale_order_id');

        try {
            $saleOrder = SaleOrder::with(['items.product.digitalProducts', 'items.digitalProducts'])
                ->findOrFail($saleOrderId);
        } catch (\Exception $e) {
            $this->error("Sale order with ID {$saleOrderId} not found.");

            return Command::FAILURE;
        }

        $this->info("Processing sale order #{$saleOrder->order_number} (ID: {$saleOrder->id})");
        $this->line("Current status: {$saleOrder->status}");
        $this->newLine();

        if ($saleOrder->status === Status::COMPLETED->value) {
            $this->warn('Sale order is already completed. No allocation needed.');

            return Command::SUCCESS;
        }

        DB::beginTransaction();
        try {
            $fullyAllocated = true;
            $allocationSummary = [];

            foreach ($saleOrder->items as $item) {
                $alreadyAllocated = $item->digitalProducts()->count();
                $remaining = $item->quantity - $alreadyAllocated;

                if ($remaining <= 0) {
                    $allocationSummary[] = [$item->product->name, $item->quantity, $alreadyAllocated, 0, 'Already fulfilled'];
                    continue;
                }

                $allocated = $this->digitalProductAllocationService->allocate($item, $item->product, $remaining);

                $allocationSummary[] = [
                    $item->product->name,
                    $item->quantity,
                    $alreadyAllocated,
                    $allocated,
                    $allocated >= $remaining ? 'Fulfilled' : 'Partial (insufficient stock)',
                ];

                if ($allocated < $remaining) {
                    $fullyAllocated = false;
                }
            }

            $this->table(
                ['Product', 'Qty Ordered', 'Previously Allocated', 'Newly Allocated', 'Status'],
                $allocationSummary,
            );
            $this->newLine();

            if ($fullyAllocated) {
                $saleOrder->update(['status' => Status::COMPLETED->value]);
                DB::commit();
                event(new SaleOrderCompleted($saleOrder));
                $this->info('Sale order fully allocated and marked as COMPLETED.');

                return Command::SUCCESS;
            }

            DB::rollBack();
            $this->warn('Insufficient voucher stock to fully allocate this order. No changes were saved.');
            $this->warn('Add more vouchers to stock and retry.');

            return Command::FAILURE;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('AllocateVouchersToSaleOrderCommand: failed to allocate order', [
                'sale_order_id' => $saleOrderId,
                'error' => $e->getMessage(),
            ]);
            $this->error('Error: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
