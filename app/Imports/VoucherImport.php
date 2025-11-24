<?php

namespace App\Imports;

use RuntimeException;
use App\Models\Voucher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\VoucherCipherService;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class VoucherImport implements ToCollection, WithBatchInserts, WithChunkReading, WithHeadingRow
{
    protected int $purchaseOrderID;

    protected int $purchaseOrderTotalQuantity;

    protected array $errors = [];

    protected int $successCount = 0;

    protected int $failureCount = 0;

    protected VoucherCipherService $voucherCipherService;

    public function __construct(VoucherCipherService $voucherCipherService, int $purchaseOrderID, int $purchaseOrderTotalQuantity)
    {
        $this->purchaseOrderID = $purchaseOrderID;
        $this->purchaseOrderTotalQuantity = $purchaseOrderTotalQuantity;
        $this->voucherCipherService = $voucherCipherService;
    }

    /**
     * @param  Collection<int, \Illuminate\Support\Collection<string,mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        $totalRows = $rows->count();

        if ($totalRows !== $this->purchaseOrderTotalQuantity) {
            throw new RuntimeException("The number of voucher codes ({$totalRows}) does not match the total quantity of the purchase order ({$this->purchaseOrderTotalQuantity}).");
        }

        DB::beginTransaction();
        try {
            foreach ($rows as $row) {
                // @phpstan-ignore function.impossibleType
                $rowData = is_array($row) ? $row : $row->toArray();
                $this->validateRow($rowData);
                $code = $this->voucherCipherService->encryptCode($rowData['code']);
                Voucher::create([
                    'code' => $code,
                    'purchase_order_id' => $this->purchaseOrderID,
                    'status' => 'available',
                ]);
                $this->successCount++;
            }
            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();
            throw new RuntimeException($e->getMessage());
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
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
