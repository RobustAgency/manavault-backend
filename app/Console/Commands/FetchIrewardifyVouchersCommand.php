<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Irewardify\IrewardifyVoucherService;

class FetchIrewardifyVouchersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'irewardify:fetch-vouchers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch voucher delivery codes for pending Irewardify purchase orders';

    public function __construct(private IrewardifyVoucherService $voucherService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting Irewardify voucher fetch...');
        $this->newLine();

        try {
            $summary = $this->voucherService->processAllPurchaseOrders();

            $this->info('Processing completed!');
            $this->newLine();

            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total Orders Found', $summary['total_orders']],
                    ['Orders Processed', $summary['processed_orders']],
                    ['Orders Skipped', $summary['skipped_orders']],
                    ['Orders Failed', $summary['failed_orders']],
                    ['Total Vouchers Added', $summary['total_vouchers_added']],
                ]
            );

            if (! empty($summary['errors'])) {
                $this->newLine();
                $this->warn('Errors encountered:');
                foreach ($summary['errors'] as $error) {
                    $this->error("  Order #{$error['purchase_order_id']} ({$error['order_number']}): {$error['error']}");
                }
            }
        } catch (\Throwable $e) {
            $this->error('Voucher fetch failed: '.$e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
