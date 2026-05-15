<?php

namespace App\Console\Commands;

use App\Enums\SupplierType;
use App\Models\Supplier;
use App\Services\Supplier\SupplierIntegrationResolver;
use Illuminate\Console\Command;

class SyncSupplierProductsCommand extends Command
{
    protected $signature = 'suppliers:sync-products
                            {--supplier= : Supplier slug to sync (omit to sync all registered suppliers)}';

    protected $description = 'Sync digital products for all suppliers registered in the integration layer';

    public function __construct(
        private readonly SupplierIntegrationResolver $resolver,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $suppliers = $this->resolveSuppliersToSync();

        if ($suppliers->isEmpty()) {
            $this->warn('No matching suppliers found.');

            return Command::FAILURE;
        }

        $synced = 0;
        $failed = 0;

        foreach ($suppliers as $supplier) {
            $integration = $this->resolver->resolve($supplier);

            if ($integration === null) {
                $this->line("  <fg=gray>Skipped</> {$supplier->slug} (not yet in integration layer)");
                continue;
            }

            $this->line("  Syncing <info>{$supplier->slug}</info>...");

            try {
                $integration->syncProducts();
                $this->line("  <fg=green>Done</> {$supplier->slug}");
                $synced++;
            } catch (\Throwable $e) {
                $this->error("  Failed {$supplier->slug}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->line("Sync complete — <info>{$synced} synced</info>, <fg=red>{$failed} failed</>.");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Returns the supplier(s) to sync based on the --supplier option.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Supplier>
     */
    private function resolveSuppliersToSync()
    {
        $slug = $this->option('supplier');

        if ($slug) {
            return Supplier::where('slug', $slug)
                ->where('type', SupplierType::EXTERNAL->value)
                ->get();
        }

        return Supplier::where('type', SupplierType::EXTERNAL->value)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }
}
