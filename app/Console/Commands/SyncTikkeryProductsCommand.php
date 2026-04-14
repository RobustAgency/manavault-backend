<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Tikkery\SyncDigitalProducts;

class SyncTikkeryProductsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tikkery:sync-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync digital products from Tikkery';

    public function __construct(private SyncDigitalProducts $syncDigitalProducts)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting Tikkery product sync...');

        try {
            $this->syncDigitalProducts->processSyncAllProducts();
            $this->info('Sync completed successfully.');
        } catch (\Throwable $e) {
            $this->error('Sync failed: '.$e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
