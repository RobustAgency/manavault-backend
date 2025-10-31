<?php

namespace App\Imports;

use App\Models\Voucher;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class VoucherImport implements ToCollection, WithHeadingRow, WithBatchInserts, WithChunkReading
{
    private int $purchaseOrderID;

    public function __construct(int $purchaseOrderID)
    {
        $this->purchaseOrderID = $purchaseOrderID;
    }

    /**
     * @param Collection<int, array> $collection
     */
    public function collection(Collection $collection): void
    {
        foreach ($collection as $row) {
            // Skip empty rows
            if (empty($row['code'])) {
                continue;
            }

            Voucher::create([
                'code' => $row['code'],
                'purchase_order_id' => $this->purchaseOrderID,
            ]);
        }
    }

    public function batchSize(): int
    {
        return 100;
    }

    public function chunkSize(): int
    {
        return 100;
    }
}
