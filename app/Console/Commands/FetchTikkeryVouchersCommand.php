<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Tikkery\TikkeryVoucherService;

class FetchTikkeryVouchersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tikkery:fetch-vouchers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll pending Tikkery purchase orders and fetch their voucher codes';

    public function __construct(private TikkeryVoucherService $tikkeryVoucherService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting Tikkery voucher fetch...');
        $this->newLine();

        try {
            $summary = $this->tikkeryVoucherService->processAllPurchaseOrders();

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
                $this->error('Errors encountered:');
                $this->newLine();

                foreach ($summary['errors'] as $error) {
                    $this->line(sprintf(
                        'Order #%s (ID: %d): %s',
                        $error['order_number'],
                        $error['purchase_order_id'],
                        $error['error']
                    ));
                }
            }

            $this->newLine();

            if ($summary['failed_orders'] > 0) {
                $this->warn('Processing completed with some errors. Check the logs for details.');

                return Command::FAILURE;
            }

            $this->info('All pending Tikkery orders processed successfully!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Fatal error: '.$e->getMessage());
            $this->error('Stack trace: '.$e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}
