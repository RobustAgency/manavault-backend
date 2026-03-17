<?php

namespace App\Services\Voucher;

use App\Models\Voucher;
use App\DTOs\VoucherDTO;
use App\Models\PurchaseOrder;
use App\Events\NewVouchersAvailable;
use App\Services\PurchaseOrder\PurchaseOrderStatusService;

class VoucherCreateService
{
    public function __construct(
        private VoucherPurchaseOrderValidator $voucherPurchaseOrderValidator,
        private VoucherFileImportService $voucherFileImportService,
        private VoucherCipherService $voucherCipherService,
        private PurchaseOrderStatusService $purchaseOrderStatusService
    ) {}

    /**
     * Create vouchers from either file upload or array input
     *
     * @param  array<string, mixed>  $data
     */
    public function createVouchers(array $data): void
    {
        $purchaseOrderID = $data['purchase_order_id'];

        if (isset($data['file'])) {
            $this->voucherFileImportService->processFile($data);
        } else {
            $this->createVouchersFromArray(
                voucherData: $data['voucher_codes'],
                purchaseOrderID: $purchaseOrderID
            );
        }

        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = PurchaseOrder::findOrFail($purchaseOrderID);
        $this->purchaseOrderStatusService->updateInternalSuppliersStatusToCompleted($purchaseOrder);

        // Notify listeners that new vouchers are available so pending sale orders can be fulfilled
        $digitalProductIds = $purchaseOrder->items()->pluck('digital_product_id')->unique()->values()->all();
        event(new NewVouchersAvailable($digitalProductIds));
    }

    /**
     * Create vouchers from array input
     *
     * @param  array<int, array<string, mixed>>  $voucherData
     */
    private function createVouchersFromArray(array $voucherData, int $purchaseOrderID): bool
    {
        // Convert array data to DTOs first
        $voucherDTOs = array_map(
            fn (array $item) => VoucherDTO::fromArrayInput($item),
            $voucherData
        );

        // Validate DTOs against purchase order (includes quantity validation)
        $purchaseOrder = $this->voucherPurchaseOrderValidator->validateVoucherDTOs(
            $voucherDTOs,
            $purchaseOrderID
        );

        // Process each voucher DTO
        foreach ($voucherDTOs as $voucherDTO) {
            // Find the purchase order item for this digital product
            $purchaseOrderItem = $purchaseOrder->items->firstWhere(
                'digital_product_id',
                $voucherDTO->digital_product_id
            );

            if (! $purchaseOrderItem) {
                continue;
            }

            // Encrypt the code
            $encryptedCode = $this->voucherCipherService->encryptCode($voucherDTO->code);

            // Create voucher record
            Voucher::create([
                'purchase_order_id' => $purchaseOrderID,
                'purchase_order_item_id' => $purchaseOrderItem->id,
                'code' => $encryptedCode,
                'serial_number' => $voucherDTO->serial_number,
                'pin_code' => $voucherDTO->pin_code,
                'status' => 'available',
            ]);
        }

        return true;
    }
}
