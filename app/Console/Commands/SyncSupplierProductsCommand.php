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

            logger()->info("Syncing products for supplier: {$supplier->slug}");

            try {
                $supplierIntegration->syncProducts();
                logger()->info("Done syncing products for supplier: {$supplier->slug}");
            } catch (\Throwable $e) {
                logger()->error("Failed syncing products for supplier [{$supplier->slug}]: {$e->getMessage()}");
            }
        }

        return Command::SUCCESS;
    }
}
