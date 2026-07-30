<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Actions\Irewardify\ExportProductsCsv;

class ExportIrewardifyProductsCommand extends Command
{
    protected $signature = 'irewardify:export-products {--path= : Absolute path to write the CSV to (defaults to the private local disk)}';

    protected $description = 'Export Irewardify products (with pricing and discount details) to a CSV file';

    public function __construct(private readonly ExportProductsCsv $exportProductsCsv)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Fetching Irewardify products...');

        try {
            $csv = $this->exportProductsCsv->execute();
        } catch (\Throwable $e) {
            $this->error("Failed to export Irewardify products: {$e->getMessage()}");

            return Command::FAILURE;
        }

        $absolutePath = $this->option('path');

        if ($absolutePath) {
            if (! is_dir(dirname($absolutePath))) {
                mkdir(dirname($absolutePath), 0755, true);
            }

            file_put_contents($absolutePath, $csv);

            $this->info("Exported Irewardify products CSV to {$absolutePath}");

            return Command::SUCCESS;
        }

        $relativePath = 'exports/irewardify-products-'.now()->format('Y-m-d_His').'.csv';

        Storage::disk('local')->put($relativePath, $csv);

        $this->info('Exported Irewardify products CSV to storage/app/private/'.$relativePath);

        return Command::SUCCESS;
    }
}
