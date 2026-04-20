<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Giftery\SyncProducts;

class SyncGifteryProductsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'giftery:sync-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync products from Giftery supplier';

    public function __construct(private SyncProducts $syncProducts)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $this->info('Starting Giftery product sync...');
            $this->syncProducts->processSyncAllProducts();

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Giftery product sync failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
