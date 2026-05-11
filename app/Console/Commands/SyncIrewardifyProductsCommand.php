<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Irewardify\SyncProducts;

class SyncIrewardifyProductsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'irewardify:sync-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync digital products from Irewardify';

    public function __construct(private SyncProducts $syncProducts)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting Irewardify product sync...');

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
