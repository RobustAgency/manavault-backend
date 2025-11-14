<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EzCards\SyncDigitalProduct;

class SyncEzCardsProductsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ezcards:sync-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync products from EZ Cards supplier';

    public function __construct(private SyncDigitalProduct $syncDigitalProduct)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting EZ Cards product sync...');

        try {
            $this->syncDigitalProduct->processSyncAllProducts();
            $this->info('Sync completed successfully.');
        } catch (\Throwable $e) {
            $this->error('Sync failed: '.$e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
