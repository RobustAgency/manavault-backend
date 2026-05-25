<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use Illuminate\Console\Command;
use App\Services\Supplier\SupplierIntegrationResolver;

class SyncSupplierProductsCommand extends Command
{
    protected $signature = 'supplier:sync-products {supplier : Supplier slug}';

    protected $description = 'Sync digital products from a supplier via its integration';

    public function __construct(private readonly SupplierIntegrationResolver $resolver)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $slug = (string) $this->argument('supplier');

        $supplier = Supplier::where('slug', $slug)->first();

        $integration = $this->resolver->resolve($supplier);

        if ($integration === null) {
            $this->error("No integration registered for supplier: {$slug}");

            return Command::INVALID;
        }

        $this->info("Starting {$slug} product sync...");

        try {
            $integration->syncProducts();
            $this->info('Sync completed successfully.');
        } catch (\Throwable $e) {
            $this->error('Sync failed: '.$e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
