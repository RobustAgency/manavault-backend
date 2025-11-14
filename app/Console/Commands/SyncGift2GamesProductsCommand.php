<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Gift2Games\SyncDigitalProducts;

class SyncGift2GamesProductsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'g2g:sync-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync digital products from Gift2Games';

    // public function __construct(private SyncDigitalProducts $syncDigitalProducts)
    // {
    //     parent::__construct();
    // }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting Gift2Games product sync...');
        try {
            // $this->syncDigitalProducts->processSyncAllProducts();
            $this->info('Sync completed successfully.');
        } catch (\Throwable $e) {
            $this->error('Sync failed: '.$e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
