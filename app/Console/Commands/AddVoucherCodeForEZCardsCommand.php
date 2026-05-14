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
            $this->voucherCodeService->processAllPurchaseOrders();

            $this->info('All orders processed successfully!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Fatal error: '.$e->getMessage());
            $this->error('Stack trace: '.$e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}
