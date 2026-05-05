<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Suppliers\Support\PollOutcome;
use App\Suppliers\Support\PollIterationResult;
use App\Suppliers\Support\SupplierVoucherPoller;

class SuppliersPollVouchersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'suppliers:poll-vouchers {slug? : Supplier slug to poll; omit to poll every external supplier whose integration supports polling}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll pending purchase orders for one or all pollable external suppliers and persist any new voucher codes';

    public function __construct(private SupplierVoucherPoller $poller)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $slug = $this->argument('slug');
        $label = $slug ?? 'all pollable suppliers';

        $this->info("Starting voucher poll for {$label}...");
        $this->newLine();

        $totals = ['total' => 0, 'processed' => 0, 'skipped' => 0, 'failed' => 0, 'vouchers' => 0];
        $errors = [];

        try {
            foreach ($this->poller->pollAll($slug) as $result) {
                /** @var PollIterationResult $result */
                $totals['total']++;

                match ($result->outcome) {
                    PollOutcome::PROCESSED => [
                        $totals['processed']++,
                        $totals['vouchers'] += $result->vouchersAdded,
                    ],
                    PollOutcome::SKIPPED => $totals['skipped']++,
                    PollOutcome::FAILED => [
                        $totals['failed']++,
                        $errors[] = [
                            'purchase_order_id' => $result->purchaseOrder->id,
                            'order_number' => $result->purchaseOrder->order_number,
                            'error' => $result->error?->getMessage() ?? 'unknown error',
                        ],
                    ],
                };
            }
        } catch (\Exception $e) {
            $this->error('Fatal error: '.$e->getMessage());
            $this->error('Stack trace: '.$e->getTraceAsString());

            return Command::FAILURE;
        }

        $this->info('Processing completed!');
        $this->newLine();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Orders Found', $totals['total']],
                ['Orders Processed', $totals['processed']],
                ['Orders Skipped', $totals['skipped']],
                ['Orders Failed', $totals['failed']],
                ['Total Vouchers Added', $totals['vouchers']],
            ]
        );

        if (! empty($errors)) {
            $this->newLine();
            $this->error('Errors encountered:');
            $this->newLine();

            foreach ($errors as $error) {
                $this->line(sprintf(
                    'Order #%s (ID: %d): %s',
                    $error['order_number'],
                    $error['purchase_order_id'],
                    $error['error']
                ));
            }
        }

        $this->newLine();

        if ($totals['failed'] > 0) {
            $this->warn('Processing completed with some errors. Check the logs for details.');

            return Command::FAILURE;
        }

        $this->info("Voucher poll for {$label} completed successfully.");

        return Command::SUCCESS;
    }
}
