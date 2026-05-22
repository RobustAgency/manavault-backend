<?php

namespace App\Services\Voucher;

use App\Models\Voucher;
use App\DTOs\VoucherDTO;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Log;
use App\Events\NewVouchersAvailable;
use Illuminate\Database\QueryException;
use App\Services\PurchaseOrder\PurchaseOrderStatusService;

class VoucherCreateService
{
    public function __construct(
        private VoucherPurchaseOrderValidator $voucherPurchaseOrderValidator,
        private VoucherFileImportService $voucherFileImportService,
        private VoucherCipherService $voucherCipherService,
        private PurchaseOrderStatusService $purchaseOrderStatusService,
        private VoucherDeduplicationService $voucherDeduplicationService,
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

            $digitalProductId = $purchaseOrderItem->digital_product_id;

            if ($this->voucherDeduplicationService->isDuplicate($digitalProductId, $voucherDTO->code)) {
                Log::info('Skipping duplicate voucher code', [
                    'digital_product_id' => $digitalProductId,
                    'purchase_order_id' => $purchaseOrderID,
                ]);

                continue;
            }

            $encryptedCode = $this->voucherCipherService->encryptCode($voucherDTO->code);

            try {
                Voucher::create([
                    'purchase_order_id' => $purchaseOrderID,
                    'purchase_order_item_id' => $purchaseOrderItem->id,
                    'code' => $encryptedCode,
                    'serial_number' => $voucherDTO->serial_number,
                    'pin_code' => $voucherDTO->pin_code,
                    'status' => 'available',
                    'digital_product_id' => $digitalProductId,
                    'code_hash' => $this->voucherDeduplicationService->computeHash($voucherDTO->code),
                ]);
            } catch (QueryException $e) {
                if ($e->getCode() === '23000') {
                    Log::warning('Voucher code unique constraint violation (race condition)', [
                        'digital_product_id' => $digitalProductId,
                        'purchase_order_id' => $purchaseOrderID,
                    ]);

                    continue;
                }
                throw $e;
            }
        }

        return true;
    }
}
