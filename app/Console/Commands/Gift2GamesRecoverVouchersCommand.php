<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Gift2Games\Gift2GamesRecoverVouchersService;

class Gift2GamesRecoverVouchersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gift2games:recover-vouchers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch completed orders from Gift2Games, group by reference number (purchase order), and store any missing voucher codes';

    public function __construct(private Gift2GamesRecoverVouchersService $recoverVouchersService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Fetching orders from Gift2Games and recovering missing vouchers...');
        $this->newLine();

        try {
            $summary = $this->recoverVouchersService->recoverMissingVouchers();

            $this->info('Processing completed!');
            $this->newLine();

            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total References Found', $summary['total_references']],
                    ['References Processed', $summary['processed_references']],
                    ['References Skipped', $summary['skipped_references']],
                    ['References Failed', $summary['failed_references']],
                    ['Total Vouchers Added', $summary['total_vouchers_added']],
                ]
            );

            if (! empty($summary['errors'])) {
                $this->newLine();
                $this->error('Errors encountered:');
                $this->newLine();

                foreach ($summary['errors'] as $error) {
                    $this->line(sprintf(
                        'Reference %s: %s',
                        $error['reference'],
                        $error['error']
                    ));
                }
            }

            $this->newLine();

            if ($summary['failed_references'] > 0) {
                $this->warn('Processing completed with some errors. Check the logs for details.');

                return Command::FAILURE;
            }

            $this->info('Gift2Games voucher recovery completed successfully!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Fatal error: '.$e->getMessage());
            $this->error('Stack trace: '.$e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}
