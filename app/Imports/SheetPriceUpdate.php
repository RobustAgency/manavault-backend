<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\DigitalProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;

class SheetPriceUpdate implements ToCollection
{
    public function __construct(
        protected array &$summary,
        protected int $nameCol,
        protected int $priceCol
    ) {}

    /**
     * @param  Collection<int, array<int, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {

            $name = trim((string) ($row[$this->nameCol] ?? ''));
            $price = $row[$this->priceCol] ?? null;

            // Skip empty rows, header rows, sub-category label rows
            if (blank($name) || ! is_numeric($price) || (float) $price <= 0) {
                continue;
            }

            $this->summary['count']++;

            $product = Product::where('name', $name)->first();

            if ($product) {
                $digitalProductIds = DB::table('product_supplier')->where('product_id', $product->id)->pluck('digital_product_id')->toArray();

                $updatedCount = DigitalProduct::whereIn(
                    'id',
                    $digitalProductIds
                )->update(['selling_price' => (float) $price]);

                if ($updatedCount > 0) {
                    $this->summary['updated']++;
                } else {
                    $this->summary['not_found']++;
                }
            } else {
                $this->summary['not_found']++;
            }
        }
    }
}
