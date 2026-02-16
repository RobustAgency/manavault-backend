<?php

namespace App\Services\Ezcards;

use App\Models\Voucher;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\PurchaseOrderSupplier;
use App\Services\VoucherCipherService;
use App\Actions\Ezcards\GetVoucherCodes;
use App\Enums\PurchaseOrderSupplierStatus;
use App\Services\PurchaseOrder\PurchaseOrderStatusService;

class EzcardsVoucherCodeService
{
    public function __construct(
        private GetVoucherCodes $getVoucherCodes,
        private PurchaseOrderStatusService $purchaseOrderStatusService,
        private VoucherCipherService $voucherCipherService
    ) {}

    /**
     * Process all EZ Cards purchase orders and fetch their voucher codes.
     *
     * @return array Summary of processing results
     */
    public function processAllPurchaseOrders(): array
    {
        $summary = [
            'total_orders' => 0,
            'processed_orders' => 0,
            'skipped_orders' => 0,
            'failed_orders' => 0,
            'total_vouchers_added' => 0,
            'errors' => [],
        ];

        // Fetch all EZ Cards purchase orders that haven't been fully processed
        $purchaseOrders = $this->getUnprocessedPurchaseOrders();
        $summary['total_orders'] = $purchaseOrders->count();

        foreach ($purchaseOrders as $purchaseOrder) {
            try {
                $result = $this->processPurchaseOrder($purchaseOrder);

                if ($result['skipped']) {
                    $summary['skipped_orders']++;
                } else {
                    $summary['processed_orders']++;
                    $summary['total_vouchers_added'] += $result['vouchers_added'];
                }
            } catch (\Exception $e) {
                $summary['failed_orders']++;
                $summary['errors'][] = [
                    'purchase_order_id' => $purchaseOrder->id,
                    'order_number' => $purchaseOrder->order_number,
                    'error' => $e->getMessage(),
                ];

                Log::error('Failed to process voucher codes for purchase order', [
                    'purchase_order_id' => $purchaseOrder->id,
                    'order_number' => $purchaseOrder->order_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $summary;
    }

    /**
     * Get all unprocessed EZ Cards purchase orders.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, PurchaseOrder>
     */
    private function getUnprocessedPurchaseOrders()
    {
        // Get purchase order IDs that have EZ Cards suppliers with transaction_id
        $purchaseOrderIds = PurchaseOrderSupplier::whereHas('supplier', function ($query) {
            $query->where('slug', 'ez_cards');
        })
            ->where('status', '!=', 'completed')
            ->whereNotNull('transaction_id')
            ->pluck('purchase_order_id');

        return PurchaseOrder::with(['vouchers', 'items.digitalProduct'])
            ->whereIn('id', $purchaseOrderIds)
            ->get();
    }

    /**
     * Process a single purchase order to fetch and store voucher codes.
     *
     * @return array Processing result
     */
    public function processPurchaseOrder(PurchaseOrder $purchaseOrder): array
    {
        $result = [
            'skipped' => false,
            'vouchers_added' => 0,
            'reason' => null,
        ];

        // Get the EZ Cards purchase order supplier record
        $purchaseOrderSupplier = PurchaseOrderSupplier::where('purchase_order_id', $purchaseOrder->id)
            ->whereHas('supplier', function ($query) {
                $query->where('slug', 'ez_cards');
            })
            ->first();

        if (! $purchaseOrderSupplier || ! $purchaseOrderSupplier->transaction_id) {
            $result['skipped'] = true;
            $result['reason'] = 'No EZ Cards transaction ID found';

            return $result;
        }

        $transactionID = (int) $purchaseOrderSupplier->transaction_id;

        // Fetch voucher codes from EZ Cards API
        $voucherCodesResponse = $this->getVoucherCodes->execute($transactionID);

        // Process and store voucher codes
        $vouchersAdded = $this->storeVoucherCodes($purchaseOrder, $voucherCodesResponse);
        $result['vouchers_added'] = $vouchersAdded;

        $this->purchaseOrderStatusService->updateStatus($purchaseOrder);

        $purchaseOrderSupplier->update(['status' => PurchaseOrderSupplierStatus::COMPLETED->value]);

        return $result;
    }

    /**
     * Store voucher codes from API response into the database.
     *
     * @return int Number of vouchers added
     */
    private function storeVoucherCodes(PurchaseOrder $purchaseOrder, array $voucherCodesResponse): int
    {
        $vouchersAdded = 0;

        // Handle response structure - the data array contains items for each product
        $productVouchers = $voucherCodesResponse['data'] ?? [];

        // Load purchase order items with digital products to match vouchers to items
        $purchaseOrder->load('items.digitalProduct');

        DB::beginTransaction();
        try {
            // Process each product's vouchers from the API response
            foreach ($productVouchers as $productData) {
                $sku = $productData['sku'];
                $codes = $productData['codes'];

                // Find the corresponding purchase order item by matching the SKU
                $purchaseOrderItem = $purchaseOrder->items->first(function ($item) use ($sku) {
                    return $item->digitalProduct->sku === $sku;
                });

                if (! $purchaseOrderItem) {
                    Log::warning('No purchase order item found for EzCards voucher', [
                        'sku' => $sku,
                        'purchase_order_id' => $purchaseOrder->id,
                    ]);

                    continue;
                }

                // Process each voucher code for this product
                foreach ($codes as $voucherData) {
                    $code = $voucherData['redeemCode'] ?? null;
                    $pinCode = $voucherData['pinCode'] ?? null;
                    $stockId = $voucherData['stockId'] ?? null;
                    $apiStatus = $voucherData['status'];

                    // Determine voucher status based on API status and code availability
                    if ($apiStatus === 'COMPLETED' && $code) {
                        $status = 'available';
                    } elseif ($apiStatus === 'PROCESSING' || ! $code) {
                        $status = 'processing';
                    } else {
                        $status = 'available';
                    }

                    // For processing status without code, create placeholder with stockId
                    if (! $code && $status === 'processing' && $stockId) {
                        // Check if a voucher with this stockId already exists
                        $exists = Voucher::where('purchase_order_id', $purchaseOrder->id)
                            ->where('stock_id', $stockId)
                            ->exists();

                        if (! $exists) {
                            Voucher::create([
                                'code' => null,
                                'purchase_order_id' => $purchaseOrder->id,
                                'purchase_order_item_id' => $purchaseOrderItem->id,
                                'status' => $status,
                                'serial_number' => null,
                                'pin_code' => null,
                                'stock_id' => $stockId,
                            ]);
                            $vouchersAdded++;
                        }

                        continue;
                    }

                    // Skip if no code and not processing
                    if (! $code) {
                        continue;
                    }

                    // Check if voucher code already exists (by code or by stockId)
                    $existingVoucher = Voucher::where('purchase_order_id', $purchaseOrder->id)
                        ->where(function ($query) use ($code, $stockId) {
                            $query->where('code', $code);
                            if ($stockId) {
                                $query->orWhere('stock_id', $stockId);
                            }
                        })
                        ->first();

                    $code = $this->voucherCipherService->encryptCode($code);

                    if (! $existingVoucher) {
                        Voucher::create([
                            'code' => $code,
                            'purchase_order_id' => $purchaseOrder->id,
                            'purchase_order_item_id' => $purchaseOrderItem->id,
                            'status' => $status,
                            'pin_code' => $pinCode,
                            'stock_id' => $stockId,
                        ]);
                        $vouchersAdded++;
                    } else {
                        // Update existing voucher if status or code changed
                        $existingVoucher->update([
                            'code' => $code,
                            'purchase_order_item_id' => $purchaseOrderItem->id,
                            'status' => $status,
                            'pin_code' => $pinCode,
                            'stock_id' => $stockId,
                        ]);
                    }
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $vouchersAdded;
    }
}
