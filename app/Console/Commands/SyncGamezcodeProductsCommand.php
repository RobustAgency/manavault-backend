<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Gamezcode\SyncProducts;

class SyncGamezcodeProductsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gamezcode:sync-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync digital products from Gamezcode (Kalixo)';

    public function __construct(private SyncProducts $syncProducts)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting Gamezcode product sync...');

        try {
            $this->syncProducts->processSyncAllProducts();
            $this->info('Sync completed successfully.');
        } catch (\Throwable $e) {
            $this->error('Sync failed: '.$e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
