<?php

namespace App\Console\Commands;

use App\Models\DigitalProduct;
use Illuminate\Console\Command;

class SetDigitalProductDiscountValue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'digital-products:set-discount-value';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set discount value for digital products with null discount to 0';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $digitalProducts = DigitalProduct::whereNotNull('selling_price')->get();

        foreach ($digitalProducts as $digitalProduct) {
            $faceValue = $digitalProduct->face_value;
            $sellingPrice = $digitalProduct->selling_price;
            $selling_discount = $faceValue > 0
                    ? round((($faceValue - $sellingPrice) / $faceValue) * 100, 2)
                    : 0;

            $digitalProduct->update([
                'selling_discount' => $selling_discount,
                'selling_discount_updated_at' => now(),
            ]);
        }

        $this->info('Discount values have been set for digital products with null discount.');

    }
}
