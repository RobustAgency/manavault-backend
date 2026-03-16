<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Imports\PriceUpdateImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;

class UpdateDigitalProductSellingPrice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-selling-price';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command updates the selling price of digital products based on the xlsx file provided';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Starting price update process...');
        $import = new PriceUpdateImport;
        $path = Storage::disk('public')->path('Mana_Minds_Price_List_2026_updated.xlsx');

        Excel::import($import, $path);
        $summary = $import->summary;

        $this->info('Price update process completed.');
        $this->info('Summary:');
        $this->info("Count: {$summary['count']}");
        $this->info("Updated: {$summary['updated']}");
        $this->info("Not Found: {$summary['not_found']}");
    }
}
