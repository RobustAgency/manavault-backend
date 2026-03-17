<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PriceUpdateImport implements WithMultipleSheets
{
    public array $summary = ['count' => 0, 'updated' => 0, 'not_found' => 0];

    public function sheets(): array
    {
        // Same instance passed to all sheets so summary stays shared
        return [
            'USD' => new SheetPriceUpdate($this->summary, nameCol: 0, priceCol: 1),
            'EURO' => new SheetPriceUpdate($this->summary, nameCol: 0, priceCol: 1),
            'In-Game Currencies' => new SheetPriceUpdate($this->summary, nameCol: 0, priceCol: 1),
            'Credit Card & Crypto Vouchers' => new SheetPriceUpdate($this->summary, nameCol: 0, priceCol: 1),
            'Software' => new SheetPriceUpdate($this->summary, nameCol: 0, priceCol: 1),
            'Razer Gold' => new SheetPriceUpdate($this->summary, nameCol: 0, priceCol: 1),
        ];
    }
}
