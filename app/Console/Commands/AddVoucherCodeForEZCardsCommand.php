<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Ezcards\EzcardsVoucherCodeService;

class AddVoucherCodeForEZCardsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ezcards:add-voucher';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add voucher code for EZCards purchase orders';

    public function __construct(private EzcardsVoucherCodeService $voucherCodeService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting EZ Cards voucher code processing...');
        $this->newLine();

        try {
            $summary = $this->voucherCodeService->processAllPurchaseOrders();

            // Display summary
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

            // Display errors if any
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

            $this->info('All orders processed successfully!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Fatal error: '.$e->getMessage());
            $this->error('Stack trace: '.$e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}
