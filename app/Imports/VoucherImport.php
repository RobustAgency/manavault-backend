<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use App\Repositories\PurchaseOrderRepository;
use App\Services\Voucher\VoucherCipherService;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use App\Services\Voucher\VoucherPurchaseOrderValidator;

class VoucherImport implements ToCollection, WithBatchInserts, WithChunkReading, WithHeadingRow
{
    protected int $purchaseOrderID;

    protected int $purchaseOrderTotalQuantity;

    protected array $errors = [];

    protected int $successCount = 0;

    protected int $failureCount = 0;

    protected VoucherCipherService $voucherCipherService;

    protected VoucherPurchaseOrderValidator $validator;

    protected PurchaseOrderRepository $purchaseOrderRepo;

    public function __construct(
        int $purchaseOrderID,
        PurchaseOrderRepository $purchaseOrderRepo,
        VoucherCipherService $voucherCipherService,
        VoucherPurchaseOrderValidator $validator
    ) {
        $this->purchaseOrderID = $purchaseOrderID;
        $this->purchaseOrderRepo = $purchaseOrderRepo;
        $this->voucherCipherService = $voucherCipherService;
        $this->validator = $validator;
    }

    /**
     * @param  Collection<int, \Illuminate\Support\Collection<string,mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        $rows = $rows->toArray();
        $this->validator->validate($rows);
        foreach ($rows as $row) {
            $purchaseOrder = $this->purchaseOrderRepo->getPurchaseOrderByID($this->purchaseOrderID);
            $digitalProductID = $row['digital_product_id'];
            $purchaseOrderItem = $purchaseOrder->items->firstWhere('digital_product_id', $digitalProductID);

            $data = [
                'purchase_order_id' => $this->purchaseOrderID,
                'purchase_order_item_id' => $purchaseOrderItem->id,
                'code' => $this->voucherCipherService->encryptCode($row['code']),
                'serial_number' => $row['serial_number'] ?? null,
                'pin_code' => $row['pin_code'] ?? null,
                'status' => 'available',
            ];
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
