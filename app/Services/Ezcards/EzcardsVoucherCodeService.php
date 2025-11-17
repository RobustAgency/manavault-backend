<?php

namespace App\Services\Ezcards;

use App\Models\Voucher;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Actions\Ezcards\GetVoucherCodes;

class EzcardsVoucherCodeService
{
    public function __construct(
        private GetVoucherCodes $getVoucherCodes
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
        return PurchaseOrder::with(['supplier', 'vouchers', 'items'])
            ->whereHas('supplier', function ($query) {
                $query->where('slug', 'ez_cards');
            })
            ->where('status', '!=', 'completed')
            ->whereNotNull('transaction_id')
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

        $transactionID = (int) $purchaseOrder->transaction_id;

        // Fetch voucher codes from EZ Cards API
        $voucherCodesResponse = $this->getVoucherCodes->execute($transactionID);

        // Process and store voucher codes
        $vouchersAdded = $this->storeVoucherCodes($purchaseOrder, $voucherCodesResponse);
        $result['vouchers_added'] = $vouchersAdded;

        // Check if all vouchers are now completed and update the purchase order
        if ($this->areAllVouchersCompleted($purchaseOrder)) {
            $this->markPurchaseOrderAsProcessed($purchaseOrder);
        }

        return $result;
    }

    /**
     * Check if all voucher codes for a purchase order are completed.
     */
    private function areAllVouchersCompleted(PurchaseOrder $purchaseOrder): bool
    {
        // Refresh the vouchers relationship
        $purchaseOrder->load('vouchers');

        $totalVouchers = $purchaseOrder->vouchers()->count();

        // If no vouchers exist yet, return false
        if ($totalVouchers === 0) {
            return false;
        }

        // Get the total quantity from all purchase order items
        $expectedQuantity = $purchaseOrder->getTotalQuantity();

        // Check if we have the expected number of vouchers
        if ($totalVouchers < $expectedQuantity) {
            return false;
        }

        // Check if all vouchers have available status
        $availableVouchers = $purchaseOrder->vouchers()
            ->where('status', 'available')
            ->count();

        return $availableVouchers === $expectedQuantity;
    }

    /**
     * Store voucher codes from API response into the database.
     *
     * @return int Number of vouchers added
     */
    private function storeVoucherCodes(PurchaseOrder $purchaseOrder, array $voucherCodesResponse): int
    {
        $vouchersAdded = 0;

        // Handle response structure - adjust based on actual API response
        $vouchers = $voucherCodesResponse['data'][0]['codes'] ?? [];

        DB::beginTransaction();
        try {
            foreach ($vouchers as $index => $voucherData) {
                // Extract voucher code (adjust field names based on actual API response)
                $code = $voucherData['redeemCode'] ?? $voucherData['serialCode'] ?? null;
                $serialNumber = $voucherData['serialNumber'] ?? null;
                $status = isset($voucherData['redeemCode']) ? 'available' : 'processing';
                $stockId = $voucherData['stockId'] ?? null;

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
                            'status' => $status,
                            'serial_number' => null,
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

                if (! $existingVoucher) {
                    Voucher::create([
                        'code' => $code,
                        'purchase_order_id' => $purchaseOrder->id,
                        'status' => $status,
                        'serial_number' => $serialNumber,
                        'stock_id' => $stockId,
                    ]);
                    $vouchersAdded++;
                } else {
                    // Update existing voucher if status or code changed
                    $existingVoucher->update([
                        'code' => $code,
                        'status' => $status,
                        'serial_number' => $serialNumber,
                        'stock_id' => $stockId,
                    ]);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $vouchersAdded;
    }

    /**
     * Mark a purchase order as fully processed.
     */
    private function markPurchaseOrderAsProcessed(PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->update([
            'status' => 'completed',
        ]);
    }
}
