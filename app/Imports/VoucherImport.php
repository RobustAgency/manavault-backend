<?php

namespace App\Imports;

use App\Models\Voucher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use RuntimeException;

class VoucherImport implements ToCollection, WithHeadingRow, WithBatchInserts, WithChunkReading
{
    protected int $purchaseOrderID;
    protected array $errors = [];
    protected int $successCount = 0;
    protected int $failureCount = 0;

    public function __construct(int $purchaseOrderID)
    {
        $this->purchaseOrderID = $purchaseOrderID;
    }

    /**
     * @param Collection<int, \Illuminate\Support\Collection<string,mixed>> $rows
     */
    public function collection(Collection $rows): void
    {
        DB::beginTransaction();
        foreach ($rows as $index => $row) {
            try {
                $this->validateRow($row->toArray());

                Voucher::create([
                    'code' => $row['code'],
                    'purchase_order_id' => $this->purchaseOrderID,
                ]);

                $this->successCount++;
            } catch (ValidationException $e) {
                DB::rollBack();
                throw new RuntimeException($e->getMessage());
            }
        }
        DB::commit();
    }

    protected function validateRow(array $data): void
    {
        $validator = Validator::make($data, [
            'code' => 'required|string|max:255|unique:vouchers,code',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
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
