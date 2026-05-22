<?php

namespace App\Imports;

use App\Models\Voucher;
use App\DTOs\VoucherDTO;
use App\Enums\VoucherCodeStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Maatwebsite\Excel\Concerns\ToCollection;
use App\Services\Voucher\VoucherCipherService;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use App\Services\Voucher\VoucherDeduplicationService;
use App\Services\Voucher\VoucherPurchaseOrderValidator;

class VoucherImport implements ToCollection, WithBatchInserts, WithChunkReading, WithHeadingRow
{
    protected int $purchaseOrderID;

    protected VoucherDeduplicationService $deduplicationService;

    public function __construct(
        int $purchaseOrderID,
    ) {
        $this->purchaseOrderID = $purchaseOrderID;
        $this->deduplicationService = app(VoucherDeduplicationService::class);
    }

    /**
     * @param  Collection<int, \Illuminate\Support\Collection<string,mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        $rows = $rows->toArray();

        // Convert rows to DTOs for consistent validation and processing
        $voucherDTOs = array_map(
            fn (array $row) => VoucherDTO::fromFileRow($row),
            $rows
        );

        $validator = new VoucherPurchaseOrderValidator;
        $purchaseOrder = $validator->validateVoucherDTOs(
            $voucherDTOs,
            $this->purchaseOrderID
        );

        foreach ($voucherDTOs as $voucherDTO) {
            // Find the purchase order item for this digital product
            $purchaseOrderItem = $purchaseOrder->items->firstWhere(
                'digital_product_id',
                $voucherDTO->digital_product_id
            );

            $digitalProductId = $purchaseOrderItem->digital_product_id;

            if ($this->deduplicationService->isDuplicate($digitalProductId, $voucherDTO->code)) {
                Log::info('Skipping duplicate voucher code during file import', [
                    'digital_product_id' => $digitalProductId,
                    'purchase_order_id' => $this->purchaseOrderID,
                ]);

                continue;
            }

            $voucherCipherService = new VoucherCipherService;
            $encryptedCode = $voucherCipherService->encryptCode($voucherDTO->code);

            try {
                Voucher::create([
                    'purchase_order_id' => $this->purchaseOrderID,
                    'purchase_order_item_id' => $purchaseOrderItem->id,
                    'code' => $encryptedCode,
                    'serial_number' => $voucherDTO->serial_number,
                    'pin_code' => $voucherDTO->pin_code,
                    'status' => VoucherCodeStatus::AVAILABLE->value,
                    'digital_product_id' => $digitalProductId,
                    'code_hash' => $this->deduplicationService->computeHash($voucherDTO->code),
                ]);
            } catch (QueryException $e) {
                if ($e->getCode() === '23000') {
                    Log::warning('Voucher code unique constraint violation during file import (race condition)', [
                        'digital_product_id' => $digitalProductId,
                        'purchase_order_id' => $this->purchaseOrderID,
                    ]);

                    continue;
                }
                throw $e;
            }
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
