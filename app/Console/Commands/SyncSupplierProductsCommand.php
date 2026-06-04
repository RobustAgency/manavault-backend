<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Enums\SupplierType;
use Illuminate\Console\Command;
use App\Services\Supplier\SupplierIntegrationResolver;

class SyncSupplierProductsCommand extends Command
{
    protected $signature = 'supplier:sync-products';

    protected $description = 'Sync digital products from all external suppliers that have an integration';

    public function __construct(private readonly SupplierIntegrationResolver $resolver)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $suppliers = Supplier::where('type', SupplierType::EXTERNAL->value)->get();

        foreach ($suppliers as $supplier) {
            $supplierIntegration = $this->resolver->resolve($supplier);

            if ($supplierIntegration === null) {
                continue;
            }

            $this->info("Syncing products for: {$supplier->slug}");

            try {
                $supplierIntegration->syncProducts();
                $this->info("Done: {$supplier->slug}");
            } catch (\Throwable $e) {
                $this->error("Failed [{$supplier->slug}]: {$e->getMessage()}");
            }
        }

        return Command::SUCCESS;
    }
}
